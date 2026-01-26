<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\Repository\Criteria\CriteriaInterface;
use InvalidArgumentException;

class QueryBuilder {
    private Connection $connection;

    private ?HydratorInterface $hydrator;

    private ?string $entityClass = null;

    private ?EntityMetadataRegistry $metadataRegistry;

    private string $from = '';

    private array $select = [];

    private array $where = []; // [['operator' => 'AND|OR', 'condition' => '...', 'params' => [...], 'group' => null|array], ...]

    private array $having = []; // [['operator' => 'AND|OR', 'condition' => '...', 'params' => [...]], ...]

    private array $joins = []; // [['sql' => '...', 'params' => [...]], ...]

    private bool $distinct = false;

    private ?string $rawSql = null;

    private array $rawParams = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private array $orderBy = [];

    private array $groupBy = [];

    private ?UnitOfWork $unitOfWork = null;

    public function __construct(
        Connection $connection,
        ?HydratorInterface $hydrator = null,
        ?EntityMetadataRegistry $metadataRegistry = null
    ) {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
        $this->entityClass = null;
        $this->metadataRegistry = $metadataRegistry;
    }

    public function createSubQueryBuilder(): QueryBuilder
    {
        return new self($this->connection, $this->hydrator, $this->metadataRegistry);
    }

    public function select(string ...$fields): self
    {
        $this->select = array_merge($this->select, $fields);

        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->from = $alias ? "{$table} {$alias}" : $table;

        return $this;
    }

    public function where(string $condition, mixed ...$params): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => $condition,
            'params' => $params,
            'group' => null,
        ];

        return $this;
    }

    public function orWhere(string $condition, mixed ...$params): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => $condition,
            'params' => $params,
            'group' => null,
        ];

        return $this;
    }

    public function whereIn(string $column, array|QueryBuilder $values): self
    {
        if ($values instanceof QueryBuilder) {
            $subquerySql = $values->getSQL();
            $subqueryParams = $values->getParameters();

            $this->where[] = [
                'operator' => 'AND',
                'condition' => "{$column} IN ({$subquerySql})",
                'params' => $subqueryParams,
                'group' => null,
            ];

            return $this;
        }

        // Handle array values (original logic)
        if (empty($values)) {
            // Empty IN clause - generate a condition that never matches
            return $this->where('1 = 0');
        } else {
            return $this->where("{$column} IN (?)", $values);
        }
    }

    public function whereNotIn(string $column, array|QueryBuilder $values): self
    {
        if ($values instanceof QueryBuilder) {
            $subquerySql = $values->getSQL();
            $subqueryParams = $values->getParameters();

            $this->where[] = [
                'operator' => 'AND',
                'condition' => "{$column} NOT IN ({$subquerySql})",
                'params' => $subqueryParams,
                'group' => null,
            ];

            return $this;
        }

        // Handle array values (original logic)
        if (empty($values)) {
            // Empty NOT IN clause - generate a condition that always matches
            return $this->where('1 = 1');
        } else {
            return $this->where("{$column} NOT IN (?)", $values);
        }
    }

    public function whereNull(string $column): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} IS NULL",
            'params' => [],
            'group' => null,
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} IS NOT NULL",
            'params' => [],
            'group' => null,
        ];

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} BETWEEN ? AND ?",
            'params' => [$min, $max],
            'group' => null,
        ];

        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} NOT BETWEEN ? AND ?",
            'params' => [$min, $max],
            'group' => null,
        ];

        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} LIKE ?",
            'params' => [$pattern],
            'group' => null,
        ];

        return $this;
    }

    public function whereNotLike(string $column, string $pattern): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} NOT LIKE ?",
            'params' => [$pattern],
            'group' => null,
        ];

        return $this;
    }

    public function whereGreaterThan(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} > ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function whereGreaterThanOrEqual(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} >= ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function whereLessThan(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} < ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function whereLessThanOrEqual(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} <= ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function whereNotEqual(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} != ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function whereExists(QueryBuilder $subquery): self
    {
        $subquerySql = $subquery->getSQL();
        $subqueryParams = $subquery->getParameters();

        $this->where[] = [
            'operator' => 'AND',
            'condition' => "EXISTS ({$subquerySql})",
            'params' => $subqueryParams,
            'group' => null,
        ];

        return $this;
    }

    public function whereGroup(CriteriaInterface $criteria): self
    {
        $groupBuilder = $this->createSubQueryBuilder();
        $criteria->apply($groupBuilder);

        if (!empty($groupBuilder->where)) {
            $this->where[] = [
                'operator' => 'AND',
                'condition' => null, // Will be computed in getSQL()
                'params' => [],
                'group' => $groupBuilder->where,
            ];
        }

        return $this;
    }

    public function whereNotGroup(CriteriaInterface $criteria): self
    {
        $groupBuilder = $this->createSubQueryBuilder();
        $criteria->apply($groupBuilder);

        if (!empty($groupBuilder->where)) {
            $groupClause = $this->buildWhereClause($groupBuilder->where);
            $this->where[] = [
                'operator' => 'AND',
                'condition' => "NOT ({$groupClause})",
                'params' => $this->collectWhereParameters($groupBuilder->where),
                'group' => null,
            ];
        }

        return $this;
    }

    public function orWhereGroup(CriteriaInterface $criteria): self
    {
        $groupBuilder = $this->createSubQueryBuilder();
        $criteria->apply($groupBuilder);

        if (!empty($groupBuilder->where)) {
            $this->where[] = [
                'operator' => 'OR',
                'condition' => null, // Will be computed in getSQL()
                'params' => [],
                'group' => $groupBuilder->where,
            ];
        }

        return $this;
    }

    public function orWhereNotGroup(CriteriaInterface $criteria): self
    {
        $groupBuilder = $this->createSubQueryBuilder();
        $criteria->apply($groupBuilder);

        if (!empty($groupBuilder->where)) {
            $groupClause = $this->buildWhereClause($groupBuilder->where);
            $this->where[] = [
                'operator' => 'OR',
                'condition' => "NOT ({$groupClause})",
                'params' => $this->collectWhereParameters($groupBuilder->where),
                'group' => null,
            ];
        }

        return $this;
    }

    public function orWhereLike(string $column, string $pattern): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} LIKE ?",
            'params' => [$pattern],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereNotLike(string $column, string $pattern): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} NOT LIKE ?",
            'params' => [$pattern],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereGreaterThan(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} > ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereGreaterThanOrEqual(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} >= ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereLessThan(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} < ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereLessThanOrEqual(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} <= ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereNotEqual(string $column, mixed $value): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "{$column} != ?",
            'params' => [$value],
            'group' => null,
        ];

        return $this;
    }

    public function orWhereExists(QueryBuilder $subquery): self
    {
        $subquerySql = $subquery->getSQL();
        $subqueryParams = $subquery->getParameters();

        $this->where[] = [
            'operator' => 'OR',
            'condition' => "EXISTS ({$subquerySql})",
            'params' => $subqueryParams,
            'group' => null,
        ];

        return $this;
    }

    // Reset methods
    public function resetWhere(): self
    {
        $this->where = [];

        return $this;
    }

    public function resetSelect(): self
    {
        $this->select = [];

        return $this;
    }

    public function resetJoins(): self
    {
        $this->joins = [];

        return $this;
    }

    public function resetOrderBy(): self
    {
        $this->orderBy = [];

        return $this;
    }

    public function resetGroupBy(): self
    {
        $this->groupBy = [];

        return $this;
    }

    public function resetHaving(): self
    {
        $this->having = [];

        return $this;
    }

    public function reset(): self
    {
        $this->select = [];
        $this->from = '';
        $this->where = [];
        $this->having = [];
        $this->joins = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->distinct = false;

        return $this;
    }

    // Aggregate functions
    public function count(string $column = '*', ?string $alias = null): self
    {
        $aggregate = "COUNT({$column})";
        if ($alias) {
            $aggregate .= " as {$alias}";
        }
        $this->select[] = $aggregate;

        return $this;
    }

    public function sum(string $column, ?string $alias = null): self
    {
        $aggregate = "SUM({$column})";
        if ($alias) {
            $aggregate .= " as {$alias}";
        }
        $this->select[] = $aggregate;

        return $this;
    }

    public function avg(string $column, ?string $alias = null): self
    {
        $aggregate = "AVG({$column})";
        if ($alias) {
            $aggregate .= " as {$alias}";
        }
        $this->select[] = $aggregate;

        return $this;
    }

    public function max(string $column, ?string $alias = null): self
    {
        $aggregate = "MAX({$column})";
        if ($alias) {
            $aggregate .= " as {$alias}";
        }
        $this->select[] = $aggregate;

        return $this;
    }

    public function min(string $column, ?string $alias = null): self
    {
        $aggregate = "MIN({$column})";
        if ($alias) {
            $aggregate .= " as {$alias}";
        }
        $this->select[] = $aggregate;

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;

        return $this;
    }

    // HAVING clause
    public function having(string $condition, mixed ...$params): self
    {
        $this->having[] = [
            'operator' => 'AND',
            'condition' => $condition,
            'params' => $params,
        ];

        return $this;
    }

    public function orHaving(string $condition, mixed ...$params): self
    {
        $this->having[] = [
            'operator' => 'OR',
            'condition' => $condition,
            'params' => $params,
        ];

        return $this;
    }

    // Raw SQL methods
    public function raw(string $sql, mixed ...$params): self
    {
        $this->rawSql = $sql;
        // Handle both single array parameter and multiple parameters
        if (count($params) === 1 && is_array($params[0])) {
            $this->rawParams = $params[0];
        } else {
            $this->rawParams = $params;
        }

        return $this;
    }

    public function selectRaw(string $expression, mixed ...$params): self
    {
        $this->select[] = ['raw' => true, 'expression' => $expression, 'params' => $params];

        return $this;
    }

    public function selectSub(QueryBuilder $subquery, ?string $alias = null): self
    {
        $subquerySql = $subquery->getSQL();
        $subqueryParams = $subquery->getParameters();

        $expression = "({$subquerySql})";
        if ($alias) {
            $expression .= " as {$alias}";
        }

        $this->select[] = ['raw' => true, 'expression' => $expression, 'params' => $subqueryParams];

        return $this;
    }

    public function whereRaw(string $condition, mixed ...$params): self
    {
        // Handle both single array parameter and multiple parameters
        if (count($params) === 1 && is_array($params[0])) {
            $actualParams = $params[0];
        } else {
            $actualParams = $params;
        }

        $this->where[] = [
            'operator' => 'AND',
            'condition' => $condition,
            'params' => $actualParams,
            'group' => null,
            'raw' => true,
        ];

        return $this;
    }

    public function join(string $table, string $condition, mixed ...$params): self
    {
        $this->joins[] = [
            'sql' => "JOIN {$table} ON {$condition}",
            'params' => $params,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $condition, mixed ...$params): self
    {
        $this->joins[] = [
            'sql' => "LEFT JOIN {$table} ON {$condition}",
            'params' => $params,
        ];

        return $this;
    }

    public function rightJoin(string $table, string $condition, mixed ...$params): self
    {
        $this->joins[] = [
            'sql' => "RIGHT JOIN {$table} ON {$condition}",
            'params' => $params,
        ];

        return $this;
    }

    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'sql' => "CROSS JOIN {$table}",
            'params' => [],
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$field} {$direction}";

        return $this;
    }

    public function groupBy(string ...$fields): self
    {
        $this->groupBy = array_merge($this->groupBy, $fields);

        return $this;
    }

    public function setHydrator(?HydratorInterface $hydrator): self
    {
        $this->hydrator = $hydrator;

        return $this;
    }

    public function getHydrator(): ?HydratorInterface
    {
        return $this->hydrator;
    }

    /**
     * Set the UnitOfWork that will manage entities retrieved by this query.
     */
    public function setUnitOfWork(?UnitOfWork $unitOfWork): self
    {
        $this->unitOfWork = $unitOfWork;

        return $this;
    }

    public function setEntityClass(string $entityClass): self
    {
        $this->entityClass = $entityClass;

        // If no FROM clause set yet, try to set it from entity
        if (empty($this->from)) {
            $this->from($this->resolveTableName($entityClass));
        }

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function getSQL(): string
    {
        // If raw SQL is set, return it directly
        if ($this->rawSql !== null) {
            return $this->rawSql;
        }

        $selectClause = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $sql = $selectClause . $this->buildSelectClause();
        $sql .= ' FROM ' . $this->from;

        if (!empty($this->joins)) {
            $joinSqls = array_column($this->joins, 'sql');
            $sql .= ' ' . implode(' ', $joinSqls);
        }

        if (!empty($this->where)) {
            $whereClause = $this->buildWhereClause($this->where);
            $sql .= ' WHERE ' . $whereClause;
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $havingClause = $this->buildHavingClause($this->having);
            $sql .= ' HAVING ' . $havingClause;
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    public function getParameters(): array
    {
        // If raw SQL is set, return raw parameters
        if ($this->rawSql !== null) {
            return $this->rawParams;
        }

        $params = [];

        // Add SELECT raw parameters (appear before joins in SQL)
        foreach ($this->select as $selectItem) {
            if (is_array($selectItem) && isset($selectItem['raw']) && $selectItem['raw']) {
                $params = array_merge($params, $selectItem['params']);
            }
        }

        // Add join parameters
        foreach ($this->joins as $join) {
            $params = array_merge($params, $join['params']);
        }

        // Add WHERE parameters
        $params = array_merge($params, $this->collectWhereParameters($this->where));

        // Add HAVING parameters
        foreach ($this->having as $havingClause) {
            $params = array_merge($params, $havingClause['params']);
        }

        return $params;
    }

    public function getResult(?string $entityClass = null): mixed
    {
        $sql = $this->getSQL();
        $params = $this->getParameters();

        $statement = $this->connection->executeQuery($sql, $params);
        $rawResults = $statement->fetchAll();

        if (empty($rawResults)) {
            return [];
        }

        // Use provided entity class, or fall back to set entity class
        $targetClass = $entityClass ?? $this->entityClass;

        // If entity class available and hydrator available, return hydrated objects
        if ($targetClass && $this->hydrator) {
            $entities = array_map(
                fn ($row) => $this->hydrator->hydrate($targetClass, $row),
                $rawResults
            );

            // Register entities with UnitOfWork if one is set
            if ($this->unitOfWork) {
                foreach ($entities as $entity) {
                    $this->unitOfWork->registerManaged($entity, []); // Empty array for original data since we don't have it
                }
            }

            return $entities;
        }

        // Otherwise return raw arrays
        return $rawResults;
    }

    public function getSingleResult(?string $entityClass = null): mixed
    {
        $this->limit(1);
        $results = $this->getResult($entityClass);

        return is_array($results) ? ($results[0] ?? null) : $results;
    }

    public function execute(): int
    {
        $sql = $this->getSQL();
        $params = $this->getParameters();

        $statement = $this->connection->executeQuery($sql, $params);

        return $statement->rowCount();
    }

    private function resolveTableName(string $entityClass): string
    {
        // Use metadata registry if available, otherwise fall back to simple pluralization
        if ($this->metadataRegistry) {
            try {
                return $this->metadataRegistry->getTableName($entityClass);
            } catch (InvalidArgumentException $e) {
                // Entity is not properly configured, fall back to simple pluralization
                // This allows QueryBuilder to work even with misconfigured entities
            }
        }

        // Fallback: simple pluralization: User -> users, Post -> posts
        $className = basename(str_replace('\\', '/', $entityClass));

        return strtolower($className) . 's';
    }

    /**
     * Build a HAVING clause from the having conditions array.
     */
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

    /**
     * Build a SELECT clause handling raw expressions.
     */
    private function buildSelectClause(): string
    {
        if (empty($this->select)) {
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
        foreach ($this->select as $selectItem) {
            if (is_array($selectItem) && isset($selectItem['raw']) && $selectItem['raw']) {
                $selectParts[] = $selectItem['expression'];
            } else {
                $selectParts[] = $selectItem;
            }
        }

        return implode(', ', $selectParts);
    }

    /**
     * Build a WHERE clause from the where conditions array.
     */
    private function buildWhereClause(array $conditions): string
    {
        $parts = [];

        foreach ($conditions as $condition) {
            if ($condition['group'] !== null) {
                // This is a group condition
                $groupClause = $this->buildWhereClause($condition['group']);
                $parts[] = "({$groupClause})";
            } else {
                // This is a regular condition
                $parts[] = $condition['condition'];
            }
        }

        // Join with operators - first condition has no operator prefix
        $result = '';
        foreach ($conditions as $index => $condition) {
            if ($index === 0) {
                // First condition - handle group or regular
                if ($condition['group'] !== null) {
                    $groupClause = $this->buildWhereClause($condition['group']);
                    $result .= "({$groupClause})";
                } else {
                    $result .= $condition['condition'];
                }
            } else {
                // Subsequent conditions with operator
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

    /**
     * Collect parameters from where conditions recursively.
     */
    private function collectWhereParameters(array $conditions): array
    {
        $params = [];

        foreach ($conditions as $condition) {
            if ($condition['group'] !== null) {
                // Recursively collect from group
                $params = array_merge($params, $this->collectWhereParameters($condition['group']));
            } else {
                // Add direct parameters
                $params = array_merge($params, $condition['params']);
            }
        }

        return $params;
    }
}
