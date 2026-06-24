<?php

namespace Articulate\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Exceptions\EmptyPropertiesListException;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnComparisonNormalizer;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;
use Articulate\Utils\ReflectionCache;
use ReflectionNamedType;

class MappingTableComparator {
    public function __construct(
        private readonly DatabaseSchemaReaderInterface $databaseSchemaReader,
        private readonly SchemaNaming $schemaNaming,
        private readonly IndexComparator $indexComparator,
    ) {
    }

    /**
     * @param array{
     *     tableName: string,
     *     ownerTable: string,
     *     targetTable: string,
     *     ownerJoinColumn: string,
     *     targetJoinColumn: string,
     *     ownerReferencedColumn: string,
     *     targetReferencedColumn: string,
     *     extraProperties: array,
     *     primaryColumns: string[]
     * } $definition
     * @param string[] $existingTables
     * @return TableCompareResult|null
     */
    public function compareManyToManyTable(array $definition, array $existingTables): ?TableCompareResult
    {
        $tableName = $definition['tableName'];

        [$operation, $columnsIndexed, $existingForeignKeys, $foreignKeysToRemove, $existingIndexes, $indexesToRemove] =
            $this->initializeMappingTableState($tableName, $existingTables);

        $requiredProperties = $this->buildRequiredProperties($definition);

        $columnsCompareResults = $this->compareColumnsForMapping($requiredProperties, $columnsIndexed, $operation);

        $foreignKeysByName = $this->compareForeignKeysForMapping(
            $tableName,
            $definition,
            $existingForeignKeys,
            $foreignKeysToRemove,
            $operation
        );

        $indexCompareResults = $this->compareIndexesForMapping($existingIndexes, $indexesToRemove, $definition, $existingForeignKeys, $operation);

        if ($operation === CompareResult::OPERATION_CREATE && empty($columnsCompareResults)) {
            throw new EmptyPropertiesListException($tableName);
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
     * Initializes the state for comparing a mapping table.
     *
     * @return array{string|null, array, array, array, array, array} Returns [
     *     operation, columnsIndexed, existingForeignKeys, foreignKeysToRemove, existingIndexes, indexesToRemove
     * ]
     */
    private function initializeMappingTableState(string $tableName, array $existingTables): array
    {
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
            $existingIndexes = $this->indexComparator->removePrimaryIndex($this->databaseSchemaReader->getTableIndexes($tableName));
            $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
        }

        return [$operation, $columnsIndexed, $existingForeignKeys, $foreignKeysToRemove, $existingIndexes, $indexesToRemove];
    }

    /**
     * Builds the required properties for a mapping table.
     */
    private function buildRequiredProperties(array $definition): array
    {
        $requiredProperties = [];
        $requiredProperties[$definition['ownerJoinColumn']] = new PropertiesData('int', false, null, null, isForeignKey: true);
        $requiredProperties[$definition['targetJoinColumn']] = new PropertiesData('int', false, null, null, isForeignKey: true);
        foreach ($definition['extraProperties'] as $extra) {
            $requiredProperties[$extra->name] = new PropertiesData($extra->type, $extra->nullable, $extra->defaultValue, $extra->length);
        }

        return $requiredProperties;
    }

    /**
     * Compares columns for a mapping table.
     *
     * @return ColumnCompareResult[]
     */
    private function compareColumnsForMapping(array $requiredProperties, array $columnsIndexed, ?string &$operation): array
    {
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
            $column = $columnsIndexed[$name];
            $result = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_UPDATE,
                $property,
                ColumnComparisonNormalizer::fromDatabaseColumn($column),
            );
            if ($result->hasChanges()) {
                $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
                $columnsCompareResults[] = $result;
            }
        }

        foreach ($columnsToDelete as $name => $column) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
            $columnsCompareResults[] = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_DELETE,
                new PropertiesData(),
                ColumnComparisonNormalizer::fromDatabaseColumn($column),
            );
        }

        return $columnsCompareResults;
    }

    /**
     * Compares foreign keys for a mapping table.
     *
     * @return ForeignKeyCompareResult[]
     */
    private function compareForeignKeysForMapping(
        string $tableName,
        array $definition,
        array $existingForeignKeys,
        array &$foreignKeysToRemove,
        ?string &$operation
    ): array {
        $foreignKeysByName = [];
        $desiredForeignKeys = [
            $this->schemaNaming->foreignKeyName($tableName, $definition['ownerJoinColumn']) => [
                'column' => $definition['ownerJoinColumn'],
                'referencedTable' => $definition['ownerTable'],
                'referencedColumn' => $definition['ownerReferencedColumn'],
            ],
            $this->schemaNaming->foreignKeyName($tableName, $definition['targetJoinColumn']) => [
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

        return $foreignKeysByName;
    }

    /**
     * Compares indexes for a mapping table.
     *
     * @return IndexCompareResult[]
     */
    private function compareIndexesForMapping(
        array $existingIndexes,
        array &$indexesToRemove,
        array $definition,
        array $existingForeignKeys,
        ?string &$operation
    ): array {
        $indexCompareResults = [];
        foreach (array_keys($indexesToRemove) as $indexName) {
            if ($this->indexComparator->shouldSkipIndexDeletion($indexName, $existingIndexes[$indexName] ?? [], $definition['primaryColumns'], $existingForeignKeys)) {
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

        return $indexCompareResults;
    }

    /**
     * @param array{
     *     tableName: string,
     *     morphName: string,
     *     typeColumn: string,
     *     idColumn: string,
     *     targetColumn: string,
     *     targetTable: string,
     *     targetReferencedColumn: string,
     *     extraProperties: array,
     *     primaryColumns: string[]
     * } $definition
     * @param string[] $existingTables
     * @return TableCompareResult|null
     */
    public function compareMorphToManyTable(array $definition, array $existingTables): ?TableCompareResult
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
            $existingIndexes = $this->indexComparator->removePrimaryIndex($this->databaseSchemaReader->getTableIndexes($tableName));
            $indexesToRemove = array_fill_keys(array_keys($existingIndexes), true);
        }

        $requiredProperties = [];
        // Add ID column (auto-increment primary key)
        $requiredProperties['id'] = new PropertiesData('int', false, null, null);
        // Add morph columns
        $requiredProperties[$definition['typeColumn']] = new PropertiesData('string', false, null, 255);
        $requiredProperties[$definition['idColumn']] = $this->resolveMorphIdColumnType($definition);
        $requiredProperties[$definition['targetColumn']] = new PropertiesData('int', false, null, null, isForeignKey: true);
        // Add extra properties
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
            $column = $columnsIndexed[$name];
            $result = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_UPDATE,
                $property,
                ColumnComparisonNormalizer::fromDatabaseColumn($column),
            );
            if ($result->hasChanges()) {
                $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
                $columnsCompareResults[] = $result;
            }
        }

        foreach ($columnsToDelete as $name => $column) {
            $operation = $operation ?? TableCompareResult::OPERATION_UPDATE;
            $columnsCompareResults[] = new ColumnCompareResult(
                $name,
                CompareResult::OPERATION_DELETE,
                new PropertiesData(),
                ColumnComparisonNormalizer::fromDatabaseColumn($column),
            );
        }

        // Handle foreign keys - only create FK to target table for polymorphic relationships
        $foreignKeysByName = [];
        $requiredForeignKeys = [
            $definition['targetColumn'] => [
                'table' => $definition['targetTable'],
                'column' => $definition['targetReferencedColumn'],
            ],
        ];

        foreach ($requiredForeignKeys as $column => $target) {
            $fkName = $this->schemaNaming->foreignKeyName($tableName, $column);
            unset($foreignKeysToRemove[$fkName]);
            if (!isset($existingForeignKeys[$fkName])) {
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $foreignKeysByName[$fkName] = new ForeignKeyCompareResult(
                    $fkName,
                    CompareResult::OPERATION_CREATE,
                    $column,
                    $target['table'],
                    $target['column'],
                );
            }
        }

        foreach ($foreignKeysToRemove as $fkName => $_) {
            $operation = $operation ?? CompareResult::OPERATION_UPDATE;
            $existingFk = $existingForeignKeys[$fkName];
            $foreignKeysByName[$fkName] = new ForeignKeyCompareResult(
                $fkName,
                CompareResult::OPERATION_DELETE,
                $existingFk['column'],
                $existingFk['referencedTable'],
                $existingFk['referencedColumn'],
            );
        }

        // Handle indexes
        $indexCompareResults = [];
        $requiredIndexes = [
            $definition['typeColumn'] . '_' . $definition['idColumn'] . '_index' => [
                'columns' => [$definition['typeColumn'], $definition['idColumn']],
                'unique' => false,
            ],
        ];

        foreach ($requiredIndexes as $indexName => $indexDef) {
            unset($indexesToRemove[$indexName]);
            if (!isset($existingIndexes[$indexName])) {
                $operation = $operation ?? CompareResult::OPERATION_UPDATE;
                $indexCompareResults[] = new IndexCompareResult(
                    $indexName,
                    CompareResult::OPERATION_CREATE,
                    $indexDef['columns'],
                    $indexDef['unique'],
                );
            }
        }

        foreach ($indexesToRemove as $indexName => $_) {
            $existingIndex = $existingIndexes[$indexName] ?? [];
            if ($this->indexComparator->shouldSkipIndexDeletion($indexName, $existingIndex, $definition['primaryColumns'], $existingForeignKeys)) {
                continue;
            }

            $operation = $operation ?? CompareResult::OPERATION_UPDATE;
            $indexCompareResults[] = new IndexCompareResult(
                $indexName,
                CompareResult::OPERATION_DELETE,
                $existingIndex['columns'] ?? [],
                $existingIndex['unique'] ?? false,
            );
        }

        if ($operation === CompareResult::OPERATION_CREATE && empty($columnsCompareResults)) {
            throw new EmptyPropertiesListException($tableName);
        }

        if (!$operation && empty($columnsCompareResults) && empty($foreignKeysByName) && empty($indexCompareResults)) {
            return null;
        }

        return new TableCompareResult(
            $tableName,
            $operation ?? CompareResult::OPERATION_UPDATE,
            $columnsCompareResults,
            $indexCompareResults,
            array_values($foreignKeysByName),
            $definition['primaryColumns'],
        );
    }

    private function resolveMorphIdColumnType(array $definition): PropertiesData
    {
        foreach ($definition['relations'] as $relation) {
            $rClass = ReflectionCache::getClass($relation->getDeclaringClassName());
            foreach ($rClass->getProperties() as $property) {
                if (empty($property->getAttributes(PrimaryKey::class))) {
                    continue;
                }
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === 'string') {
                    return new PropertiesData('string', false, null, 36, isForeignKey: true);
                }
            }
        }

        return new PropertiesData('int', false, null, null, isForeignKey: true);
    }

}
