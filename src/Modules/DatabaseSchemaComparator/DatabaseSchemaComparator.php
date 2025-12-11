<?php

namespace Articulate\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Exceptions\EmptyPropertiesList;
use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Schema\SchemaNaming;
use RuntimeException;

readonly class DatabaseSchemaComparator {
    public function __construct(
        private DatabaseSchemaReader $databaseSchemaReader,
        private SchemaNaming $schemaNaming,
    ) {}

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
                    $columnName = $property->getColumnName();
                    $propertiesIndexed = $this->mergeColumnDefinition(
                        $propertiesIndexed,
                        $columnName,
                        $property,
                        $tableName,
                    );
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
                    $this->validateRelation($data['relation']);
                    $targetEntity = new ReflectionEntity($data['relation']->getTargetEntity());
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
                if (!$column->typeMatch || !$column->isNullableMatch) {
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
                    $targetEntity = new ReflectionEntity($propertyData['relation']->getTargetEntity());
                    $foreignKeyName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $columnName);
                    $foreignKeyExists = isset($existingForeignKeys[$foreignKeyName]);
                    if ($propertyData['foreignKeyRequired']) {
                        if ($operation !== TableCompareResult::OPERATION_CREATE && isset($createdColumnsWithForeignKeys[$columnName])) {
                            unset($foreignKeysToRemove[$foreignKeyName]);
                            continue;
                        }
                        $this->validateRelation($propertyData['relation']);
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
                if ($relation instanceof ReflectionManyToMany) {
                    $this->validateManyToManyRelation($relation);
                    continue;
                }
                $this->validateRelation($relation);
            }
        }
    }

    private function validateRelation(ReflectionRelation $relation): void
    {
        if ($relation->isOneToOne()) {
            $this->validateOneToOneRelation($relation);
            return;
        }

        if ($relation->isManyToOne()) {
            $this->validateManyToOneRelation($relation);
            return;
        }

        if ($relation->isOneToMany()) {
            $this->validateOneToManyRelation($relation);
        }
    }

    private function validateOneToOneRelation(ReflectionRelation $relation): void
    {
        if (!$relation->isForeignKeyRequired()) {
            return;
        }
        if (!$relation->isOwningSide()) {
            return;
        }
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $inversedPropertyName = $relation->getInversedBy();

        if (!$inversedPropertyName) {
            return;
        }

        if (!$targetEntity->hasProperty($inversedPropertyName)) {
            throw new RuntimeException('One-to-one inverse side misconfigured: property not found');
        }

        $property = $targetEntity->getProperty($inversedPropertyName);
        $attributes = $property->getAttributes(OneToOne::class);

        if (empty($attributes)) {
            throw new RuntimeException('One-to-one inverse side misconfigured: attribute missing');
        }

        $targetProperty = $attributes[0]->newInstance();

        $inverseRequestsForeignKey = $targetProperty->ownedBy !== null && $targetProperty->foreignKey;

        if ($inverseRequestsForeignKey) {
            $ownerClass = $relation->getDeclaringClassName();
            $ownerProperty = $relation->getPropertyName();
            $inverseClass = $targetEntity->getName();
            throw new RuntimeException(sprintf(
                'One-to-one inverse side misconfigured: inverse side requests foreign key (%s::%s <-> %s::%s)',
                $ownerClass,
                $ownerProperty,
                $inverseClass,
                $inversedPropertyName,
            ));
        }

        if ($targetProperty->ownedBy === null) {
            throw new RuntimeException('One-to-one inverse side misconfigured: ownedBy is required on inverse side');
        }

        if ($targetProperty->ownedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('One-to-one inverse side misconfigured: ownedBy does not reference owning property');
        }
    }

    private function validateManyToOneRelation(ReflectionRelation $relation): void
    {
        if (!$relation->isManyToOne()) {
            return;
        }

        $inversedPropertyName = $relation->getInversedBy();
        if (!$inversedPropertyName) {
            return;
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->hasProperty($inversedPropertyName)) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: property not found');
        }

        $targetProperty = $targetEntity->getProperty($inversedPropertyName);
        if (!empty($targetProperty->getAttributes(ManyToOne::class))) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: inverse side marked as owner');
        }
        $attributes = $targetProperty->getAttributes(OneToMany::class);

        if (empty($attributes)) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: attribute missing');
        }

        $inverseRelation = new ReflectionRelation($attributes[0]->newInstance(), $targetProperty);
        $mappedBy = $inverseRelation->getMappedBy();

        if ($mappedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: ownedBy does not reference owning property');
        }

        if ($inverseRelation->getTargetEntity() !== $relation->getDeclaringClassName()) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: target entity mismatch');
        }
    }

    private function validateOneToManyRelation(ReflectionRelation $relation): void
    {
        if (!$relation->isOneToMany()) {
            return;
        }

        $mappedBy = $relation->getMappedBy();
        if (!$mappedBy) {
            throw new RuntimeException('One-to-many inverse side misconfigured: ownedBy is required');
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->hasProperty($mappedBy)) {
            throw new RuntimeException('One-to-many inverse side misconfigured: owning property not found');
        }

        $targetProperty = $targetEntity->getProperty($mappedBy);
        $attributes = $targetProperty->getAttributes(ManyToOne::class);

        if (empty($attributes)) {
            throw new RuntimeException('One-to-many inverse side misconfigured: owning property not many-to-one');
        }

        $owningRelation = new ReflectionRelation($attributes[0]->newInstance(), $targetProperty);

        if ($owningRelation->getTargetEntity() !== $relation->getDeclaringClassName()) {
            throw new RuntimeException('One-to-many inverse side misconfigured: target entity mismatch');
        }

        $inversedBy = $owningRelation->getInversedBy();
        if ($inversedBy && $inversedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('One-to-many inverse side misconfigured: inversedBy does not reference inverse property');
        }
    }

    private function validateManyToManyRelation(ReflectionManyToMany $relation): void
    {
        if ($relation->getMappedBy() && $relation->getInversedBy()) {
            throw new RuntimeException('Many-to-many misconfigured: ownedBy and referencedBy cannot be both defined');
        }

        if (!$relation->isOwningSide() && count($relation->getExtraProperties()) > 0) {
            throw new RuntimeException('Many-to-many misconfigured: inverse side cannot define extra mapping properties');
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $mappedBy = $relation->getMappedBy();
        $inversedBy = $relation->getInversedBy();

        if ($relation->isOwningSide()) {
            if (!$inversedBy) {
                return;
            }
            if (!$targetEntity->hasProperty($inversedBy)) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: property not found');
            }
            $targetProperty = $targetEntity->getProperty($inversedBy);
            $attributes = $targetProperty->getAttributes(ManyToMany::class);
            if (empty($attributes)) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: attribute missing');
            }
            /** @var ManyToMany $targetAttr */
            $targetAttr = $attributes[0]->newInstance();
            $targetOwnedBy = $targetAttr->ownedBy;
            if ($targetOwnedBy !== $relation->getPropertyName()) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: ownedBy does not reference owning property');
            }
            if (
                $targetAttr->mappingTable
                && $targetAttr->mappingTable->name
                && $relation->getAttribute()->mappingTable?->name
                && $targetAttr->mappingTable->name !== $relation->getTableName()
            ) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: mapping table name mismatch');
            }
            return;
        }

        if (!$mappedBy) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: ownedBy is required');
        }
        if (!$targetEntity->hasProperty($mappedBy)) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: owning property not found');
        }
        $targetProperty = $targetEntity->getProperty($mappedBy);
        $attributes = $targetProperty->getAttributes(ManyToMany::class);
        if (empty($attributes)) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: owning property attribute missing');
        }
        /** @var ManyToMany $targetAttr */
        $targetAttr = $attributes[0]->newInstance();
        if ($targetAttr->ownedBy !== null) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: owning property cannot declare ownedBy');
        }
        if ($targetAttr->referencedBy && $targetAttr->referencedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: referencedBy does not reference inverse property');
        }
        if (
            $targetAttr->mappingTable
            && $targetAttr->mappingTable->name
            && $relation->getAttribute()->mappingTable?->name
            && $targetAttr->mappingTable->name !== $relation->getTableName()
        ) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: mapping table name mismatch');
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
            $incomingTarget = new ReflectionEntity($incoming['relation']->getTargetEntity());
            $existingTarget = new ReflectionEntity($existing['relation']->getTargetEntity());
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
            static fn(array $fk) => strtolower($fk['column']),
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
                fn($t) => $t instanceof \ReflectionNamedType && $t->getName() !== 'null',
            );
            $first = reset($nonNull);
            return $first ? $first->getName() : null;
        }
        return (string) $type;
    }
}
