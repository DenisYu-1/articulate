<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Modules\EntityManager\EntityMetadata;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;

class SoftDeleteFilter
{
    public function __construct(
        private readonly ?EntityMetadataRegistry $metadataRegistry
    ) {
    }

    public function apply(
        array $where,
        ?string $entityClass,
        bool $enabled,
        bool $includeDeleted,
        ?string $rawSql
    ): array {
        if ($rawSql !== null || !$enabled || $includeDeleted || $entityClass === null || $this->metadataRegistry === null) {
            return $where;
        }

        try {
            $metadata = $this->metadataRegistry->getMetadata($entityClass);
            if (!$metadata->isSoftDeleteable()) {
                return $where;
            }

            $softDeleteColumn = $metadata->getSoftDeleteColumn();
            if ($softDeleteColumn === null) {
                return $where;
            }

            if ($this->hasSoftDeleteFilterInWhere($where, $softDeleteColumn)) {
                return $where;
            }

            $where[] = [
                'operator' => 'AND',
                'condition' => "{$softDeleteColumn} IS NULL",
                'params' => [],
                'group' => null,
            ];
        } catch (\InvalidArgumentException $e) {
        }

        return $where;
    }

    private function hasSoftDeleteFilterInWhere(array $where, string $softDeleteColumn): bool
    {
        foreach ($where as $condition) {
            if ($condition['group'] !== null) {
                if ($this->hasSoftDeleteFilterInGroup($condition['group'], $softDeleteColumn)) {
                    return true;
                }
            } elseif (isset($condition['condition']) && str_contains($condition['condition'], $softDeleteColumn) && str_contains($condition['condition'], 'IS NULL')) {
                return true;
            }
        }

        return false;
    }

    private function hasSoftDeleteFilterInGroup(array $group, string $softDeleteColumn): bool
    {
        foreach ($group as $condition) {
            if ($condition['group'] !== null) {
                if ($this->hasSoftDeleteFilterInGroup($condition['group'], $softDeleteColumn)) {
                    return true;
                }
            } elseif (isset($condition['condition']) && str_contains($condition['condition'], $softDeleteColumn) && str_contains($condition['condition'], 'IS NULL')) {
                return true;
            }
        }

        return false;
    }
}
