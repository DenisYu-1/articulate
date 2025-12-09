<?php

namespace Articulate\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
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

        $entitiesIndexed = $this->indexByTableName($entities);
        $indexesFetched = false;

        foreach ($entitiesIndexed as $tableName => $entities) {
            $operation = null;

            $existingIndexes = $indexesToRemove = [];

            $existingForeignKeys = [];
            $foreignKeysToRemove = [];

            if (!in_array($tableName, $existingTables)) {
                $operation = TableCompareResult::OPERATION_CREATE;
            } else {
                if (!$indexesFetched) {
                    $existingIndexes = $this->databaseSchemaReader->getTableIndexes($tableName);
                    $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
                    $indexesFetched = true;
                }
                $existingForeignKeys = $this->databaseSchemaReader->getTableForeignKeys($tableName);
                $foreignKeysToRemove = array_fill_keys(array_keys($existingForeignKeys), true);
            }
            unset($tablesToRemove[$tableName]);

            $columns = $this->databaseSchemaReader->getTableColumns($tableName);
            $columnsIndexed = [];
            foreach ($columns as $column) {
                $columnsIndexed[$column->name] = $column;
            }

            $entityIndexes =
            $propertiesIndexed = [];

            /** @var ReflectionEntity $entity */
            foreach ($entities as $entity) {
                // Check if the class has Index attributes
                foreach ($entity->getAttributes(Index::class) as $indexAttribute) {
                    /** @var Index $indexInstance */
                    $indexInstance = $indexAttribute->newInstance();
                    $indexInstance->resolveColumns($entity);
                    $indexName = $indexInstance->getName();
                    $entityIndexes[$indexName] = $indexInstance;
                }
                foreach ($entity->getEntityProperties() as $property) {
                    $propertiesIndexed[$property->getColumnName()] = $property;
                }
            }

            $columnsToDelete = array_diff_key($columnsIndexed, $propertiesIndexed);
            $columnsToCreate = array_diff_key($propertiesIndexed, $columnsIndexed);
            $columnsToUpdate = array_intersect_key($propertiesIndexed, $columnsIndexed);

            $columnsCompareResults = [];
            $foreignKeys = [];
            $foreignKeysByName = [];
            $existingIndexesLoaded = isset($existingIndexes);
            $createdColumnsWithForeignKeys = [];

            foreach ($columnsToCreate as $columnName => $data) {
                $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
                $columnsCompareResults[] = new ColumnCompareResult(
                    $columnName,
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData(
                        $propertiesIndexed[$columnName]->getType(),
                        $propertiesIndexed[$columnName]->isNullable(),
                        $propertiesIndexed[$columnName]->getDefaultValue(),
                        $propertiesIndexed[$columnName]->getLength(),
                    ),
                    new PropertiesData(),
                );
                if ($data instanceof ReflectionRelation && $data->isForeignKeyRequired()) {
                    $this->validateRelation($data);
                    $targetEntity = new ReflectionEntity($data->getTargetEntity());
                    $fkName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $columnName);
                    $foreignKeysByName[$fkName] = new ForeignKeyCompareResult(
                        $fkName,
                        CompareResult::OPERATION_CREATE,
                        $columnName,
                        $targetEntity->getTableName(),
                        $data->getReferencedColumnName(),
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
                        $propertiesIndexed[$columnName]->getType(),
                        $propertiesIndexed[$columnName]->isNullable(),
                        $propertiesIndexed[$columnName]->getDefaultValue(),
                        $propertiesIndexed[$columnName]->getLength(),
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


            // Check if any index changes are needed
            $indexCompareResults = [];

            // Compare the indexes between the entity and the existing table indexes
            if (!empty($entityIndexes) || !empty($indexesToRemove)) {
                if (!$existingIndexesLoaded) {
                    $existingIndexes = $this->databaseSchemaReader->getTableIndexes($tableName);
                    $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
                    $existingIndexesLoaded = true;
                }
            }
            foreach ($entityIndexes as $indexName => $indexInstance) {
                if (!isset($existingIndexes[$indexName])) {
                    // If the index doesn't exist in the database, it needs to be created
                    $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                    $indexCompareResults[] = new IndexCompareResult(
                        $indexName,
                        CompareResult::OPERATION_CREATE,
                        $indexInstance->columns,
                        $indexInstance->unique,
                    );
                } else {
                    // If the index exists, remove it from the $indexesToRemove list (no need to delete)
                    unset($indexesToRemove[$indexName]);
                }
            }

            // Any remaining indexes in $indexesToRemove should be dropped
            foreach (array_keys($indexesToRemove) as $indexName) {
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $indexCompareResults[] = new IndexCompareResult(
                    $indexName,
                    CompareResult::OPERATION_DELETE,
                    $existingIndexes[$indexName]['columns'],
                    false,
                );
            }

            if (isset($existingForeignKeys)) {
                foreach ($propertiesIndexed as $property) {
                    if (empty($existingForeignKeys) && $operation === TableCompareResult::OPERATION_CREATE) {
                        continue;
                    }
                    if (!$property instanceof ReflectionRelation) {
                        continue;
                    }
                    $targetEntity = new ReflectionEntity($property->getTargetEntity());
                    $foreignKeyName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $property->getColumnName());
                    $foreignKeyExists = isset($existingForeignKeys[$foreignKeyName]);
                if ($property->isForeignKeyRequired()) {
                    if ($operation !== TableCompareResult::OPERATION_CREATE && isset($createdColumnsWithForeignKeys[$property->getColumnName()])) {
                        unset($foreignKeysToRemove[$foreignKeyName]);
                        continue;
                    }
                        $this->validateRelation($property);
                        $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                        if (!$foreignKeyExists) {
                            $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                                $foreignKeyName,
                                CompareResult::OPERATION_CREATE,
                                $property->getColumnName(),
                                $targetEntity->getTableName(),
                                $property->getReferencedColumnName(),
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
                $entity->getPrimaryKeyColumns(),
            );
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

        if ($targetProperty->mainSide) {
            throw new RuntimeException('One-to-one inverse side misconfigured: inverse side marked as main');
        }

        if ($targetProperty->foreignKey) {
            throw new RuntimeException('One-to-one inverse side misconfigured: inverse side requests foreign key');
        }

        if ($targetProperty->mappedBy && $targetProperty->mappedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('One-to-one inverse side misconfigured: mappedBy does not reference main property');
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
            throw new RuntimeException('Many-to-one inverse side misconfigured: mappedBy does not reference owning property');
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
            throw new RuntimeException('One-to-many inverse side misconfigured: mappedBy is required');
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
}
