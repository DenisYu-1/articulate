<?php

namespace Articulate\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Exceptions\EmptyPropertiesList;
use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Schema\SchemaNaming;
use RuntimeException;

readonly class DatabaseSchemaComparator
{
    public function __construct(
        private DatabaseSchemaReader $databaseSchemaReader,
        private SchemaNaming $schemaNaming,
        private RelationValidatorFactory $relationValidatorFactory = new RelationValidatorFactory(),
    ) {
    }

    /**
     * @param ReflectionEntity[] $entities
     * @return iterable<TableCompareResult>
     */
    public function compareAll(array $entities): iterable
    {
        $existingTables = $this->databaseSchemaReader->getTables();
        $tablesToRemove = array_fill_keys($existingTables, true);

        $this->validateRelations($entities);
        $manyToManyTables = $this->collectManyToManyTables($entities);

        $entitiesIndexed = $this->indexByTableName($entities);
        foreach ($entitiesIndexed as $tableName => $entityGroup) {
            $operation = null;

            $existingIndexes = $indexesToRemove = [];
            $existingForeignKeys = [];
            $foreignKeysToRemove = [];

            if (!in_array($tableName, $existingTables, true)) {
                $operation = TableCompareResult::OPERATION_CREATE;
            } else {
                $existingIndexes = $this->removePrimaryIndex($this->databaseSchemaReader->getTableIndexes($tableName));
                $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
                $existingForeignKeys = $this->databaseSchemaReader->getTableForeignKeys($tableName);
                $foreignKeysToRemove = array_fill_keys(array_keys($existingForeignKeys), true);
            }
            unset($tablesToRemove[$tableName]);

            $columns = $this->databaseSchemaReader->getTableColumns($tableName);
            $columnsIndexed = [];
            foreach ($columns as $column) {
                $columnsIndexed[$column->name] = $column;
            }

            $entityIndexes = $propertiesIndexed = [];
            $primaryColumns = $entityGroup[0]->getPrimaryKeyColumns();

            /** @var ReflectionEntity $entity */
            foreach ($entityGroup as $entity) {
                foreach ($entity->getAttributes(Index::class) as $indexAttribute) {
                    /** @var Index $indexInstance */
                    $indexInstance = $indexAttribute->newInstance();
                    $indexInstance->resolveColumns($entity);
                    $indexName = $indexInstance->getName();
                    $entityIndexes[$indexName] = $indexInstance;
                }
                foreach ($entity->getEntityProperties() as $property) {
                    if ($property instanceof ReflectionRelation && $property->isMorphTo()) {
                        // Handle MorphTo relations specially - they generate two columns
                        $propertiesIndexed = $this->addMorphToColumns($propertiesIndexed, $property, $tableName);

                        // Auto-generate index for polymorphic relations
                        $this->addPolymorphicIndex($entityIndexes, $property, $tableName);
                    } elseif ($property instanceof ReflectionRelation && ($property->isMorphOne() || $property->isMorphMany())) {
                        // MorphOne and MorphMany are inverse relations - they don't generate columns
                        // Just validate them
                        $validator = $this->relationValidatorFactory->getValidator($property);
                        $validator->validate($property);
                    } else {
                        $columnName = $property->getColumnName();
                        $propertiesIndexed = $this->mergeColumnDefinition(
                            $propertiesIndexed,
                            $columnName,
                            $property,
                            $tableName,
                        );
                    }
                }
            }

            $columnsToDelete = array_diff_key($columnsIndexed, $propertiesIndexed);
            $columnsToCreate = array_diff_key($propertiesIndexed, $columnsIndexed);
            $columnsToUpdate = array_intersect_key($propertiesIndexed, $columnsIndexed);

            $columnsCompareResults = [];
            $foreignKeysByName = [];
            $existingIndexesLoaded = isset($existingIndexes);
            $createdColumnsWithForeignKeys = [];

            foreach ($columnsToCreate as $columnName => $data) {
                $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
                $columnsCompareResults[] = new ColumnCompareResult(
                    $columnName,
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData(
                        $data['type'],
                        $data['nullable'],
                        $data['default'],
                        $data['length'],
                    ),
                    new PropertiesData(),
                );
                if ($data['relation'] && $data['foreignKeyRequired']) {
                    $validator = $this->relationValidatorFactory->getValidator($data['relation']);
                    $validator->validate($data['relation']);
                    $targetEntityClass = $data['relation']->getTargetEntity();
                    if ($targetEntityClass === null) {
                        continue;
                    }
                    $targetEntity = new ReflectionEntity($targetEntityClass);
                    $fkName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $columnName);
                    $foreignKeysByName[$fkName] = new ForeignKeyCompareResult(
                        $fkName,
                        CompareResult::OPERATION_CREATE,
                        $columnName,
                        $targetEntity->getTableName(),
                        $data['referencedColumn'],
                    );
                    $createdColumnsWithForeignKeys[$columnName] = true;
                }
            }

            foreach ($columnsToUpdate as $columnName => $data) {
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $column = new ColumnCompareResult(
                    $columnName,
                    CompareResult::OPERATION_UPDATE,
                    new PropertiesData(
                        $data['type'],
                        $data['nullable'],
                        $data['default'],
                        $data['length'],
                    ),
                    new PropertiesData(
                        $columnsIndexed[$columnName]->type,
                        $columnsIndexed[$columnName]->isNullable,
                        $columnsIndexed[$columnName]->defaultValue,
                        $columnsIndexed[$columnName]->length,
                    ),
                );
                if (!$column->typeMatch || !$column->isNullableMatch || !$column->isDefaultValueMatch || !$column->isLengthMatch) {
                    $columnsCompareResults[] = $column;
                }
            }

            foreach ($columnsToDelete as $columnName => $data) {
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $columnsCompareResults[] = new ColumnCompareResult(
                    $columnName,
                    CompareResult::OPERATION_DELETE,
                    new PropertiesData(),
                    new PropertiesData(
                        $columnsIndexed[$columnName]->type,
                        $columnsIndexed[$columnName]->isNullable,
                        $columnsIndexed[$columnName]->defaultValue,
                        $columnsIndexed[$columnName]->length,
                    ),
                );
            }

            $indexCompareResults = [];

            foreach ($entityIndexes as $indexName => $indexInstance) {
                if (!isset($existingIndexes[$indexName])) {
                    $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                    $indexCompareResults[] = new IndexCompareResult(
                        $indexName,
                        CompareResult::OPERATION_CREATE,
                        $indexInstance->columns,
                        $indexInstance->unique,
                    );
                } else {
                    unset($indexesToRemove[$indexName]);
                }
            }

            foreach (array_keys($indexesToRemove) as $indexName) {
                if ($this->shouldSkipIndexDeletion($indexName, $existingIndexes[$indexName] ?? [], $primaryColumns, $existingForeignKeys ?? [])) {
                    unset($indexesToRemove[$indexName]);

                    continue;
                }
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $indexCompareResults[] = new IndexCompareResult(
                    $indexName,
                    CompareResult::OPERATION_DELETE,
                    $existingIndexes[$indexName]['columns'],
                    $existingIndexes[$indexName]['unique'] ?? false,
                );
            }

            if (isset($existingForeignKeys)) {
                foreach ($propertiesIndexed as $columnName => $propertyData) {
                    if (empty($existingForeignKeys) && $operation === TableCompareResult::OPERATION_CREATE) {
                        continue;
                    }
                    if (!$propertyData['relation']) {
                        continue;
                    }
                    $targetEntityClass = $propertyData['relation']->getTargetEntity();
                    if ($targetEntityClass === null) {
                        continue;
                    }
                    $targetEntity = new ReflectionEntity($targetEntityClass);
                    $foreignKeyName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $columnName);
                    $foreignKeyExists = isset($existingForeignKeys[$foreignKeyName]);
                    if ($propertyData['foreignKeyRequired']) {
                        if ($operation !== TableCompareResult::OPERATION_CREATE && isset($createdColumnsWithForeignKeys[$columnName])) {
                            unset($foreignKeysToRemove[$foreignKeyName]);

                            continue;
                        }
                        $validator = $this->relationValidatorFactory->getValidator($propertyData['relation']);
                        $validator->validate($propertyData['relation']);
                        $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                        if (!$foreignKeyExists) {
                            $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                                $foreignKeyName,
                                CompareResult::OPERATION_CREATE,
                                $columnName,
                                $targetEntity->getTableName(),
                                $propertyData['referencedColumn'],
                            );
                        } else {
                            unset($foreignKeysToRemove[$foreignKeyName]);
                        }
                    } else {
                        if ($foreignKeyExists) {
                            $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                            unset($foreignKeysToRemove[$foreignKeyName]);
                            $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                                $foreignKeyName,
                                CompareResult::OPERATION_DELETE,
                                $existingForeignKeys[$foreignKeyName]['column'],
                                $existingForeignKeys[$foreignKeyName]['referencedTable'],
                                $existingForeignKeys[$foreignKeyName]['referencedColumn'],
                            );
                        }
                    }
                }

                foreach (array_keys($foreignKeysToRemove ?? []) as $foreignKeyName) {
                    $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                    $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                        $foreignKeyName,
                        CompareResult::OPERATION_DELETE,
                        $existingForeignKeys[$foreignKeyName]['column'],
                        $existingForeignKeys[$foreignKeyName]['referencedTable'],
                        $existingForeignKeys[$foreignKeyName]['referencedColumn'],
                    );
                }
            }
            $foreignKeys = array_values($foreignKeysByName);

            if ($operation === CompareResult::OPERATION_CREATE && empty($columnsCompareResults)) {
                throw new EmptyPropertiesList($tableName);
            }

            if (!$operation || (empty($columnsCompareResults) && empty($indexCompareResults) && empty($foreignKeys))) {
                yield from [];

                continue;
            }
            yield new TableCompareResult(
                $tableName,
                $operation,
                $columnsCompareResults,
                $indexCompareResults,
                $foreignKeys,
                $entityGroup[0]->getPrimaryKeyColumns(),
            );
        }

        foreach ($manyToManyTables as $definition) {
            unset($tablesToRemove[$definition['tableName']]);
            $compareResult = $this->compareMappingTable($definition, $existingTables);
            if ($compareResult !== null) {
                yield $compareResult;
            }
        }

        foreach (array_keys($tablesToRemove) as $tableName) {
            yield new TableCompareResult(
                $tableName,
                TableCompareResult::OPERATION_DELETE,
            );
        }
    }

    /**
     * @return ReflectionEntity[]
     */
    private function indexByTableName(array $entities): array
    {
        $entitiesIndexed = [];
        foreach ($entities as $entity) {
            if (!$entity->isEntity()) {
                continue;
            }
            $tableName = $entity->getTableName();
            if (!isset($entitiesIndexed[$tableName])) {
                $entitiesIndexed[$tableName] = [];
            }
            $entitiesIndexed[$tableName][] = $entity;
        }

        return $entitiesIndexed;
    }

    private function validateRelations(array $entities): void
    {
        foreach ($entities as $entity) {
            foreach ($entity->getEntityRelationProperties() as $relation) {
                $validator = $this->relationValidatorFactory->getValidator($relation);
                $validator->validate($relation);
            }
        }
    }

    private function collectManyToManyTables(array $entities): array
    {
        $definitions = [];
        foreach ($entities as $entity) {
            foreach ($entity->getEntityRelationProperties() as $relation) {
                if (!$relation instanceof ReflectionManyToMany) {
                    continue;
                }
                if (!$relation->isOwningSide()) {
                    continue;
                }
                $ownerEntity = new ReflectionEntity($relation->getDeclaringClassName());
                $targetEntity = new ReflectionEntity($relation->getTargetEntity());
                $tableName = $relation->getTableName();

                if (!isset($definitions[$tableName])) {
                    $definitions[$tableName] = [
                        'tableName' => $tableName,
                        'ownerTable' => $ownerEntity->getTableName(),
                        'targetTable' => $targetEntity->getTableName(),
                        'ownerJoinColumn' => $relation->getOwnerJoinColumn(),
                        'targetJoinColumn' => $relation->getTargetJoinColumn(),
                        'ownerReferencedColumn' => $relation->getOwnerPrimaryColumn(),
                        'targetReferencedColumn' => $relation->getTargetPrimaryColumn(),
                        'extraProperties' => $relation->getExtraProperties(),
                        'primaryColumns' => $relation->getPrimaryColumns(),
                    ];

                    continue;
                }

                $existing = $definitions[$tableName];
                if ($existing['ownerJoinColumn'] !== $relation->getOwnerJoinColumn() || $existing['targetJoinColumn'] !== $relation->getTargetJoinColumn()) {
                    throw new RuntimeException('Many-to-many misconfigured: conflicting mapping table definition');
                }
                $definitions[$tableName]['extraProperties'] = $this->mergeMappingTableProperties(
                    $existing['extraProperties'],
                    $relation->getExtraProperties(),
                    $tableName,
                );
            }
        }

        return $definitions;
    }

    private function compareMappingTable(array $definition, array $existingTables): ?TableCompareResult
    {
        $tableName = $definition['tableName'];
        $operation = null;

        $columnsIndexed = [];
        $existingForeignKeys = [];
        $foreignKeysToRemove = [];
        $existingIndexes = [];
        $indexesToRemove = [];

        if (!in_array($tableName, $existingTables, true)) {
            $operation = TableCompareResult::OPERATION_CREATE;
        } else {
            $existingColumns = $this->databaseSchemaReader->getTableColumns($tableName);
            foreach ($existingColumns as $column) {
                $columnsIndexed[$column->name] = $column;
            }
            $existingForeignKeys = $this->databaseSchemaReader->getTableForeignKeys($tableName);
            $foreignKeysToRemove = array_fill_keys(array_keys($existingForeignKeys), true);
            $existingIndexes = $this->removePrimaryIndex($this->databaseSchemaReader->getTableIndexes($tableName));
            $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
        }

        $requiredProperties = [];
        $requiredProperties[$definition['ownerJoinColumn']] = new PropertiesData('int', false, null, null);
        $requiredProperties[$definition['targetJoinColumn']] = new PropertiesData('int', false, null, null);
        foreach ($definition['extraProperties'] as $extra) {
            $requiredProperties[$extra->name] = new PropertiesData($extra->type, $extra->nullable, $extra->defaultValue, $extra->length);
        }

        $columnsCompareResults = [];
        $columnsToDelete = array_diff_key($columnsIndexed, $requiredProperties);
        $columnsToCreate = array_diff_key($requiredProperties, $columnsIndexed);
        $columnsToUpdate = array_intersect_key($requiredProperties, $columnsIndexed);

        foreach ($columnsToCreate as $name => $property) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
            $columnsCompareResults[] = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_CREATE,
                $property,
                new PropertiesData(),
            );
        }

        foreach ($columnsToUpdate as $name => $property) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
            $column = $columnsIndexed[$name];
            $result = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_UPDATE,
                $property,
                new PropertiesData($column->type, $column->isNullable, $column->defaultValue, $column->length),
            );
            if (!$result->typeMatch || !$result->isNullableMatch || !$result->isDefaultValueMatch || !$result->isLengthMatch) {
                $columnsCompareResults[] = $result;
            }
        }

        foreach ($columnsToDelete as $name => $column) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
            $columnsCompareResults[] = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_DELETE,
                new PropertiesData(),
                new PropertiesData($column->type, $column->isNullable, $column->defaultValue, $column->length),
            );
        }

        $foreignKeysByName = [];
        $desiredForeignKeys = [
            $this->schemaNaming->foreignKeyName($tableName, $definition['ownerTable'], $definition['ownerJoinColumn']) => [
                'column' => $definition['ownerJoinColumn'],
                'referencedTable' => $definition['ownerTable'],
                'referencedColumn' => $definition['ownerReferencedColumn'],
            ],
            $this->schemaNaming->foreignKeyName($tableName, $definition['targetTable'], $definition['targetJoinColumn']) => [
                'column' => $definition['targetJoinColumn'],
                'referencedTable' => $definition['targetTable'],
                'referencedColumn' => $definition['targetReferencedColumn'],
            ],
        ];

        foreach ($desiredForeignKeys as $name => $fk) {
            if (!isset($existingForeignKeys[$name])) {
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $foreignKeysByName[$name] = new ForeignKeyCompareResult(
                    $name,
                    CompareResult::OPERATION_CREATE,
                    $fk['column'],
                    $fk['referencedTable'],
                    $fk['referencedColumn'],
                );
            } else {
                unset($foreignKeysToRemove[$name]);
            }
        }

        foreach (array_keys($foreignKeysToRemove) as $name) {
            $operation = $operation ?? CompareResult::OPERATION_UPDATE;
            $foreignKeysByName[$name] = new ForeignKeyCompareResult(
                $name,
                CompareResult::OPERATION_DELETE,
                $existingForeignKeys[$name]['column'],
                $existingForeignKeys[$name]['referencedTable'],
                $existingForeignKeys[$name]['referencedColumn'],
            );
        }

        $indexCompareResults = [];
        foreach (array_keys($indexesToRemove) as $indexName) {
            if ($this->shouldSkipIndexDeletion($indexName, $existingIndexes[$indexName] ?? [], $definition['primaryColumns'], $existingForeignKeys ?? [])) {
                unset($indexesToRemove[$indexName]);

                continue;
            }
            $operation = $operation ?? CompareResult::OPERATION_UPDATE;
            $indexCompareResults[] = new IndexCompareResult(
                $indexName,
                CompareResult::OPERATION_DELETE,
                $existingIndexes[$indexName]['columns'] ?? [],
                $existingIndexes[$indexName]['unique'] ?? false,
            );
        }

        if ($operation === CompareResult::OPERATION_CREATE && empty($columnsCompareResults)) {
            throw new EmptyPropertiesList($tableName);
        }

        if (!$operation && empty($columnsCompareResults) && empty($foreignKeysByName) && empty($indexCompareResults)) {
            return null;
        }

        return new TableCompareResult(
            $tableName,
            $operation ?? CompareResult::OPERATION_UPDATE,
            array_values($columnsCompareResults),
            $indexCompareResults,
            array_values($foreignKeysByName),
            $definition['primaryColumns'],
        );
    }

    /**
     * @param MappingTableProperty[] $existing
     * @param MappingTableProperty[] $incoming
     * @return MappingTableProperty[]
     */
    private function mergeMappingTableProperties(array $existing, array $incoming, string $tableName): array
    {
        $properties = [];
        foreach ($existing as $property) {
            $properties[$property->name] = $property;
        }
        foreach ($incoming as $property) {
            if (!isset($properties[$property->name])) {
                $properties[$property->name] = $property;

                continue;
            }
            $current = $properties[$property->name];
            if ($current->type !== $property->type || $current->length !== $property->length || $current->defaultValue !== $property->defaultValue) {
                throw new RuntimeException(
                    sprintf(
                        'Many-to-many misconfigured: mapping table "%s" property "%s" conflicts between relations',
                        $tableName,
                        $property->name,
                    ),
                );
            }
            if ($property->nullable && !$current->nullable) {
                $properties[$property->name] = new MappingTableProperty(
                    name: $current->name,
                    type: $current->type,
                    nullable: true,
                    length: $current->length,
                    defaultValue: $current->defaultValue,
                );
            }
        }

        return array_values($properties);
    }

    /**
     * @param array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     * }> $propertiesIndexed
     * @return array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     * }>
     */
    private function mergeColumnDefinition(array $propertiesIndexed, string $columnName, ReflectionProperty|ReflectionRelation $property, string $tableName): array
    {
        $incoming = [
            'type' => $this->normalizeTypeName($property->getType()),
            'nullable' => $property->isNullable(),
            'default' => $property->getDefaultValue(),
            'length' => $property->getLength(),
            'relation' => $property instanceof ReflectionRelation ? $property : null,
            'foreignKeyRequired' => $property instanceof ReflectionRelation ? $property->isForeignKeyRequired() : false,
            'referencedColumn' => $property instanceof ReflectionRelation ? $property->getReferencedColumnName() : null,
        ];

        if (!isset($propertiesIndexed[$columnName])) {
            $propertiesIndexed[$columnName] = $incoming;

            return $propertiesIndexed;
        }

        $existing = $propertiesIndexed[$columnName];

        if ($incoming['type'] !== $existing['type'] || $incoming['length'] !== $existing['length'] || $incoming['default'] !== $existing['default']) {
            throw new RuntimeException(
                sprintf(
                    'Column "%s" on table "%s" conflicts between entities',
                    $columnName,
                    $tableName,
                ),
            );
        }

        if (($incoming['relation'] !== null) !== ($existing['relation'] !== null)) {
            throw new RuntimeException(
                sprintf(
                    'Column "%s" on table "%s" conflicts between relation and scalar definitions',
                    $columnName,
                    $tableName,
                ),
            );
        }

        if ($incoming['relation'] && $existing['relation']) {
            $incomingTargetClass = $incoming['relation']->getTargetEntity();
            $existingTargetClass = $existing['relation']->getTargetEntity();

            // Skip comparison for relations that don't have specific target entities (like MorphTo)
            if ($incomingTargetClass !== null && $existingTargetClass !== null) {
                $incomingTarget = new ReflectionEntity($incomingTargetClass);
                $existingTarget = new ReflectionEntity($existingTargetClass);
                if ($incomingTarget->getTableName() !== $existingTarget->getTableName() || $incoming['referencedColumn'] !== $existing['referencedColumn']) {
                    throw new RuntimeException(
                        sprintf(
                            'Relation column "%s" on table "%s" points to different targets',
                            $columnName,
                            $tableName,
                        ),
                    );
                }
            }
        }

        $propertiesIndexed[$columnName] = [
            'type' => $existing['type'],
            'nullable' => $existing['nullable'] || $incoming['nullable'],
            'default' => $existing['default'],
            'length' => $existing['length'],
            'relation' => $existing['relation'] ?? $incoming['relation'],
            'foreignKeyRequired' => $existing['foreignKeyRequired'] || $incoming['foreignKeyRequired'],
            'referencedColumn' => $existing['referencedColumn'] ?? $incoming['referencedColumn'],
        ];

        return $propertiesIndexed;
    }

    private function addMorphToColumns(array $propertiesIndexed, ReflectionRelation $relation, string $tableName): array
    {
        // Add the morph type column
        $typeColumnName = $relation->getMorphTypeColumnName();
        $typeColumnData = [
            'type' => 'string',
            'nullable' => false,
            'default' => null,
            'length' => 255,
            'relation' => null, // Type column has no relation
            'foreignKeyRequired' => false,
            'referencedColumn' => null,
        ];

        if (isset($propertiesIndexed[$typeColumnName])) {
            // Merge with existing definition
            $existing = $propertiesIndexed[$typeColumnName];
            if ($typeColumnData['type'] !== $existing['type'] || $typeColumnData['length'] !== $existing['length']) {
                throw new RuntimeException(
                    sprintf('Morph type column "%s" conflicts on table "%s"', $typeColumnName, $tableName)
                );
            }
        } else {
            $propertiesIndexed[$typeColumnName] = $typeColumnData;
        }

        // Add the morph ID column
        $idColumnName = $relation->getMorphIdColumnName();
        $idColumnData = [
            'type' => 'int',
            'nullable' => false,
            'default' => null,
            'length' => null,
            'relation' => $relation, // ID column has the relation for potential future FK generation
            'foreignKeyRequired' => false, // Polymorphic relations don't use traditional FK constraints
            'referencedColumn' => $relation->getReferencedColumnName(),
        ];

        if (isset($propertiesIndexed[$idColumnName])) {
            // Merge with existing definition
            $existing = $propertiesIndexed[$idColumnName];
            if ($idColumnData['type'] !== $existing['type']) {
                throw new RuntimeException(
                    sprintf('Morph ID column "%s" conflicts on table "%s"', $idColumnName, $tableName)
                );
            }
            $propertiesIndexed[$idColumnName] = [
                'type' => $idColumnData['type'],
                'nullable' => $existing['nullable'] && $idColumnData['nullable'],
                'default' => $idColumnData['default'] ?? $existing['default'],
                'length' => $idColumnData['length'] ?? $existing['length'],
                'relation' => $idColumnData['relation'] ?? $existing['relation'],
                'foreignKeyRequired' => $idColumnData['foreignKeyRequired'] || $existing['foreignKeyRequired'],
                'referencedColumn' => $idColumnData['referencedColumn'] ?? $existing['referencedColumn'],
            ];
        } else {
            $propertiesIndexed[$idColumnName] = $idColumnData;
        }

        return $propertiesIndexed;
    }

    private function addPolymorphicIndex(array &$entityIndexes, ReflectionRelation $relation, string $tableName): void
    {
        $indexName = $relation->getPropertyName() . '_morph_index';
        $indexColumns = [
            $relation->getMorphTypeColumnName(),
            $relation->getMorphIdColumnName(),
        ];

        // Create an index object compatible with the existing code
        $entityIndexes[$indexName] = new class($indexColumns) {
            public function __construct(public array $columns)
            {
            }

            public bool $unique = false;
        };
    }

    private function removePrimaryIndex(array $indexes): array
    {
        foreach (array_keys($indexes) as $name) {
            if (strtolower($name) === 'primary') {
                unset($indexes[$name]);
            }
        }

        return $indexes;
    }

    private function shouldSkipIndexDeletion(string $indexName, array $indexData, array $primaryColumns, array $existingForeignKeys): bool
    {
        $columns = $indexData['columns'] ?? [];
        if (empty($columns)) {
            return false;
        }
        $columnsLower = array_map('strtolower', $columns);

        if (!empty($primaryColumns)) {
            $primaryLower = array_map('strtolower', $primaryColumns);
            if ($columnsLower === $primaryLower) {
                return true;
            }
        }

        $fkColumns = array_map(
            static fn (array $fk) => strtolower($fk['column']),
            $existingForeignKeys,
        );
        if (count($columnsLower) === 1 && in_array($columnsLower[0], $fkColumns, true)) {
            return true;
        }

        return false;
    }

    private function normalizeTypeName(string|\ReflectionType|null $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if (is_string($type)) {
            $clean = ltrim($type, '?');
            $clean = str_replace('|null', '', $clean);

            return $clean;
        }
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof \ReflectionUnionType) {
            $nonNull = array_filter(
                $type->getTypes(),
                fn ($t) => $t instanceof \ReflectionNamedType && $t->getName() !== 'null',
            );
            $first = reset($nonNull);

            return $first ? $first->getName() : null;
        }

        return (string) $type;
    }
}
