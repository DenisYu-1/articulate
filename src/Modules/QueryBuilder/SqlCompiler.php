<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use InvalidArgumentException;

class SqlCompiler {
    private ?EntityMetadataRegistry $metadataRegistry;

    private ?string $entityClass = null;

    public function __construct(?EntityMetadataRegistry $metadataRegistry = null)
    {
        $this->metadataRegistry = $metadataRegistry;
    }

    public function setEntityClass(?string $entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    public function compile(
        ?string $rawSql,
        array $rawParams,
        string $from,
        array $select,
        array $joins,
        array $where,
        array $groupBy,
        array $having,
        array $orderBy,
        ?int $limit,
        ?int $offset,
        bool $distinct,
        bool $lockForUpdate
    ): array {
        if ($rawSql !== null) {
            return [$rawSql, $rawParams];
        }

        $selectClause = $distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $sql = $selectClause . $this->buildSelectClause($select);
        $sql .= ' FROM ' . $from;

        if (!empty($joins)) {
            $joinSqls = array_column($joins, 'sql');
            $sql .= ' ' . implode(' ', $joinSqls);
        }

        if (!empty($where)) {
            $whereClause = $this->buildWhereClause($where);
            $sql .= ' WHERE ' . $whereClause;
        }

        if (!empty($groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $groupBy);
        }

        if (!empty($having)) {
            $havingClause = $this->buildHavingClause($having);
            $sql .= ' HAVING ' . $havingClause;
        }

        if (!empty($orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $orderBy);
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }

        if ($lockForUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $params = $this->collectParameters($select, $joins, $where, $having);

        return [$sql, $params];
    }

    private function buildSelectClause(array $select): string
    {
        if (empty($select)) {
            if ($this->entityClass && $this->metadataRegistry) {
                try {
                    $columns = $this->metadataRegistry
                        ->getMetadata($this->entityClass)
                        ->getColumnNames();
                    if (!empty($columns)) {
                        return implode(', ', $columns);
                    }
                } catch (InvalidArgumentException $e) {
                }
            }

            return '*';
        }

        $selectParts = [];
        foreach ($select as $selectItem) {
            if (is_array($selectItem) && isset($selectItem['raw']) && $selectItem['raw']) {
                $selectParts[] = $selectItem['expression'];
            } else {
                $selectParts[] = $selectItem;
            }
        }

        return implode(', ', $selectParts);
    }

    public function buildWhereClause(array $conditions): string
    {
        $result = '';
        foreach ($conditions as $index => $condition) {
            if ($index === 0) {
                if ($condition['group'] !== null) {
                    $groupClause = $this->buildWhereClause($condition['group']);
                    $result .= "({$groupClause})";
                } else {
                    $result .= $condition['condition'];
                }
            } else {
                $operator = $condition['operator'];
                if ($condition['group'] !== null) {
                    $groupClause = $this->buildWhereClause($condition['group']);
                    $result .= " {$operator} ({$groupClause})";
                } else {
                    $result .= " {$operator} {$condition['condition']}";
                }
            }
        }

        return $result;
    }

    private function buildHavingClause(array $conditions): string
    {
        $result = '';

        foreach ($conditions as $index => $condition) {
            if ($index === 0) {
                $result .= $condition['condition'];
            } else {
                $operator = $condition['operator'];
                $result .= " {$operator} {$condition['condition']}";
            }
        }

        return $result;
    }

    private function collectParameters(array $select, array $joins, array $where, array $having): array
    {
        $params = [];

        foreach ($select as $selectItem) {
            if (is_array($selectItem) && isset($selectItem['raw']) && $selectItem['raw']) {
                $params = array_merge($params, $selectItem['params']);
            }
        }

        foreach ($joins as $join) {
            $params = array_merge($params, $join['params']);
        }

        $params = array_merge($params, $this->collectWhereParameters($where));

        foreach ($having as $havingClause) {
            $params = array_merge($params, $havingClause['params']);
        }

        return $params;
    }

    private function collectWhereParameters(array $conditions): array
    {
        $params = [];

        foreach ($conditions as $condition) {
            if ($condition['group'] !== null) {
                $params = array_merge($params, $this->collectWhereParameters($condition['group']));
            } else {
                $params = array_merge($params, $condition['params']);
            }
        }

        return $params;
    }

    public function collectWhereParametersPublic(array $conditions): array
    {
        return $this->collectWhereParameters($conditions);
    }
}
