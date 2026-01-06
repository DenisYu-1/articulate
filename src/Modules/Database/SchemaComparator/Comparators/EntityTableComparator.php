<?php

namespace Articulate\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Exceptions\EmptyPropertiesList;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;

readonly class EntityTableComparator
{
    public function __construct(
        private DatabaseSchemaReaderInterface $databaseSchemaReader,
        private ColumnComparator $columnComparator,
        private IndexComparator $indexComparator,
        private ForeignKeyComparator $foreignKeyComparator,
    ) {
    }

    /**
     * @param ReflectionEntity[] $entityGroup
     * @param string[] $existingTables
     * @return TableCompareResult|null
     */
    public function compareEntityTable(array $entityGroup, array $existingTables, string $tableName): ?TableCompareResult
    {
        $operation = null;

        $existingIndexes = $indexesToRemove = [];
        $existingForeignKeys = [];
        $foreignKeysToRemove = [];

        if (!in_array($tableName, $existingTables, true)) {
            $operation = TableCompareResult::OPERATION_CREATE;
        } else {
            $existingIndexes = $this->indexComparator->removePrimaryIndex($this->databaseSchemaReader->getTableIndexes($tableName));
            $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
            $existingForeignKeys = $this->databaseSchemaReader->getTableForeignKeys($tableName);
            $foreignKeysToRemove = array_fill_keys(array_keys($existingForeignKeys), true);
        }

        // Try to get columns, but handle the case where table doesn't exist
        try {
            $columns = $this->databaseSchemaReader->getTableColumns($tableName);
        } catch (\Exception $e) {
            // Table doesn't exist or other error, treat as no columns
            $columns = [];
        }

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
                    $propertiesIndexed = $this->columnComparator->addMorphToColumns($propertiesIndexed, $property, $tableName);

                    // Auto-generate index for polymorphic relations
                    $this->indexComparator->addPolymorphicIndex($entityIndexes, $property);
                } elseif ($property instanceof ReflectionRelation && ($property->isMorphOne() || $property->isMorphMany())) {
                    // MorphOne and MorphMany are inverse relations - they don't generate columns
                    // Just validate them - validation is now handled by RelationDefinitionCollector
                } else {
                    $columnName = $property->getColumnName();
                    $propertiesIndexed = $this->columnComparator->mergeColumnDefinition(
                        $propertiesIndexed,
                        $columnName,
                        $property,
                        $tableName,
                    );
                }
            }
        }

        $columnsCompareResults = $this->columnComparator->compareColumns($propertiesIndexed, $columnsIndexed);

        if (!empty($columnsCompareResults)) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
        }

        $createdColumnsWithForeignKeys = [];
        $foreignKeysFromNewColumns = $this->foreignKeyComparator->createForeignKeysForNewColumns(
            array_intersect_key($propertiesIndexed, array_flip(array_map(
                fn(ColumnCompareResult $result) => $result->columnName,
                array_filter($columnsCompareResults, fn(ColumnCompareResult $result) => $result->operation === CompareResult::OPERATION_CREATE)
            ))),
            $createdColumnsWithForeignKeys,
            $tableName
        );

        $indexCompareResults = $this->indexComparator->compareIndexes(
            $entityIndexes,
            $existingIndexes,
            $indexesToRemove,
            $primaryColumns,
            $existingForeignKeys
        );

        if (!empty($indexCompareResults)) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
        }

        $foreignKeys = $this->foreignKeyComparator->compareForeignKeys(
            $propertiesIndexed,
            $existingForeignKeys,
            $foreignKeysToRemove,
            $createdColumnsWithForeignKeys,
            $tableName,
            $operation === TableCompareResult::OPERATION_CREATE
        );

        // Merge foreign keys from new columns
        $foreignKeys = array_merge($foreignKeys, $foreignKeysFromNewColumns);

        if (!empty($foreignKeys)) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
        }

        if ($operation === CompareResult::OPERATION_CREATE && empty($columnsCompareResults)) {
            throw new EmptyPropertiesList($tableName);
        }

        if (!$operation || (empty($columnsCompareResults) && empty($indexCompareResults) && empty($foreignKeys))) {
            return null;
        }

        return new TableCompareResult(
            $tableName,
            $operation,
            $columnsCompareResults,
            $indexCompareResults,
            $foreignKeys,
            $entityGroup[0]->getPrimaryKeyColumns(),
        );
    }
}
