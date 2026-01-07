<?php

namespace Articulate\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;

readonly class IndexComparator {
    /**
     * @param array<string, object> $entityIndexes
     * @param array<string, array> $existingIndexes
     * @param array<string, bool> $indexesToRemove
     * @return array<IndexCompareResult>
     */
    public function compareIndexes(array $entityIndexes, array $existingIndexes, array &$indexesToRemove, array $primaryColumns, array $existingForeignKeys): array
    {
        $results = [];

        // Create indexes
        foreach ($entityIndexes as $indexName => $indexInstance) {
            if (!isset($existingIndexes[$indexName])) {
                $results[] = new IndexCompareResult(
                    $indexName,
                    CompareResult::OPERATION_CREATE,
                    $indexInstance->columns,
                    $indexInstance->unique,
                    $indexInstance->concurrent ?? false,
                );
            } else {
                unset($indexesToRemove[$indexName]);
            }
        }

        // Delete indexes
        foreach (array_keys($indexesToRemove) as $indexName) {
            if ($this->shouldSkipIndexDeletion($indexName, $existingIndexes[$indexName] ?? [], $primaryColumns, $existingForeignKeys)) {
                unset($indexesToRemove[$indexName]);

                continue;
            }
            $results[] = new IndexCompareResult(
                $indexName,
                CompareResult::OPERATION_DELETE,
                $existingIndexes[$indexName]['columns'],
                $existingIndexes[$indexName]['unique'] ?? false,
            );
        }

        return $results;
    }

    /**
     * @param array<string, array> $indexes
     * @return array<string, array>
     */
    public function removePrimaryIndex(array $indexes): array
    {
        foreach (array_keys($indexes) as $name) {
            if (strtolower($name) === 'primary') {
                unset($indexes[$name]);
            }
        }

        return $indexes;
    }

    /**
     * @param array<string, object> $entityIndexes
     */
    public function addPolymorphicIndex(array &$entityIndexes, ReflectionRelation $relation): void
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

    public function shouldSkipIndexDeletion(string $indexName, array $indexData, array $primaryColumns, array $existingForeignKeys): bool
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
}
