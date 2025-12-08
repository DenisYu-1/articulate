<?php

namespace Articulate\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Exceptions\EmptyPropertiesList;
use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;

readonly class DatabaseSchemaComparator {
    public function __construct(
        private DatabaseSchemaReader $databaseSchemaReader,
    ) {}

    /**
     * @param ReflectionEntity[] $entities
     * @return iterable<TableCompareResult>
     */
    public function compareAll(array $entities): iterable
    {
        $existingTables = $this->databaseSchemaReader->getTables();
        $tablesToRemove = array_fill_keys($existingTables, true);

        $entitiesIndexed = $this->indexByTableName($entities);

        foreach ($entitiesIndexed as $tableName => $entities) {
            $operation = null;

            $existingIndexes = $indexesToRemove = [];

            $existingForeignKeys = [];
            $foreignKeysToRemove = [];

            if (!in_array($tableName, $existingTables)) {
                $operation = TableCompareResult::OPERATION_CREATE;
            } else {
                $existingIndexes = $this->databaseSchemaReader->getTableIndexes($tableName);
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
                    $this->validateOneToOneRelation($data);
                    $targetEntity = new ReflectionEntity($data->getTargetEntity());
                    $foreignKeys[] = new ForeignKeyCompareResult(
                        $this->buildForeignKeyName($tableName, $targetEntity->getTableName(), $columnName),
                        CompareResult::OPERATION_CREATE,
                        $columnName,
                        $targetEntity->getTableName(),
                        $data->getReferencedColumnName(),
                    );
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
                    $foreignKeyName = $this->buildForeignKeyName($tableName, $targetEntity->getTableName(), $property->getColumnName());
                    $foreignKeyExists = isset($existingForeignKeys[$foreignKeyName]);
                    if ($property->isForeignKeyRequired()) {
                        $this->validateOneToOneRelation($property);
                        $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                        if (!$foreignKeyExists) {
                            $foreignKeys[] = new ForeignKeyCompareResult(
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
                            $foreignKeys[] = new ForeignKeyCompareResult(
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
                    $foreignKeys[] = new ForeignKeyCompareResult(
                        $foreignKeyName,
                        CompareResult::OPERATION_DELETE,
                        $existingForeignKeys[$foreignKeyName]['column'],
                        $existingForeignKeys[$foreignKeyName]['referencedTable'],
                        $existingForeignKeys[$foreignKeyName]['referencedColumn'],
                    );
                }
            }

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

    private function buildForeignKeyName(string $table, string $referencedTable, string $column): string
    {
        return sprintf('fk_%s_%s_%s', $table, $referencedTable, $column);
    }

    private function validateOneToOneRelation(ReflectionRelation $relation): void
    {
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $inversedPropertyName = $relation->getInversedBy();

        if (!$inversedPropertyName) {
            return;
        }

        if (!$targetEntity->hasProperty($inversedPropertyName)) {
            throw new \RuntimeException('One-to-one inverse side misconfigured: property not found');
        }

        $property = $targetEntity->getProperty($inversedPropertyName);
        $attributes = $property->getAttributes(OneToOne::class);

        if (empty($attributes)) {
            throw new \RuntimeException('One-to-one inverse side misconfigured: attribute missing');
        }

        $targetProperty = $attributes[0]->newInstance();

        if ($targetProperty->mainSide) {
            throw new \RuntimeException('One-to-one inverse side misconfigured: inverse side marked as main');
        }

        if ($targetProperty->foreignKey) {
            throw new \RuntimeException('One-to-one inverse side misconfigured: inverse side requests foreign key');
        }

        if ($targetProperty->mappedBy && $targetProperty->mappedBy !== $relation->getPropertyName()) {
            throw new \RuntimeException('One-to-one inverse side misconfigured: mappedBy does not reference main property');
        }
    }
}
