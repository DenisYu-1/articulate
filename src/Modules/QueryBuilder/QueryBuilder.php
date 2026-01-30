<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\EntityManager\EntityMetadata;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Exceptions\CursorPaginationException;
use Articulate\Modules\QueryBuilder\Cursor;
use Articulate\Modules\QueryBuilder\CursorCodec;
use Articulate\Modules\QueryBuilder\CursorDirection;
use Articulate\Modules\Repository\Criteria\CriteriaInterface;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

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

    private ?Cursor $cursor = null;

    private ?int $cursorLimit = null;

    private CursorCodec $cursorCodec;

    private CursorPaginationHandler $cursorPaginationHandler;

    private array $groupBy = [];

    private ?UnitOfWork $unitOfWork = null;

    private bool $lockForUpdate = false;

    private QueryResultCache $resultCache;

    private QueryResultExecutor $resultExecutor;

    private SqlCompiler $sqlCompiler;

    private SoftDeleteFilter $softDeleteFilter;

    private bool $softDeleteEnabled = true;

    private bool $includeDeleted = false;

    private ?string $dmlCommand = null;

    private array $insertColumns = [];

    private array $insertValues = [];

    private array $updateSet = [];

    private ?object $dmlEntity = null;

    private array $returning = [];

    public function __construct(
        Connection $connection,
        ?HydratorInterface $hydrator = null,
        ?EntityMetadataRegistry $metadataRegistry = null,
        ?CacheItemPoolInterface $resultCache = null
    ) {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
        $this->entityClass = null;
        $this->metadataRegistry = $metadataRegistry;
        $this->resultCache = new QueryResultCache($resultCache);
        $this->sqlCompiler = new SqlCompiler($metadataRegistry);
        $this->cursorCodec = new CursorCodec();
        $this->softDeleteFilter = new SoftDeleteFilter($metadataRegistry);
        $this->cursorPaginationHandler = new CursorPaginationHandler($this->cursorCodec, $metadataRegistry);
        $this->resultExecutor = new QueryResultExecutor($connection, $this->resultCache, $hydrator, null);
    }

    public function createSubQueryBuilder(): QueryBuilder
    {
        $subBuilder = new self($this->connection, $this->hydrator, $this->metadataRegistry, null);
        $subBuilder->sqlCompiler->setEntityClass($this->entityClass);
        $subBuilder->setSoftDeleteEnabled($this->softDeleteEnabled);
        $subBuilder->includeDeleted = $this->includeDeleted;
        return $subBuilder;
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

    public function where(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (is_callable($columnOrCallback)) {
            return $this->whereGroupCallback($columnOrCallback, 'AND');
        }

        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $condition = $this->buildConditionFromOperator($columnOrCallback, $operator, $value);
        $this->where[] = [
            'operator' => 'AND',
            'condition' => $condition['condition'],
            'params' => $condition['params'],
            'group' => null,
        ];

        return $this;
    }

    private function buildConditionFromOperator(string $column, string $operator, mixed $value): array
    {
        $operator = strtolower(trim($operator));

        return match ($operator) {
            '=', 'eq' => ['condition' => "{$column} = ?", 'params' => [$value]],
            '!=', '<>', 'ne' => ['condition' => "{$column} != ?", 'params' => [$value]],
            '>', 'gt' => ['condition' => "{$column} > ?", 'params' => [$value]],
            '<', 'lt' => ['condition' => "{$column} < ?", 'params' => [$value]],
            '>=', 'gte' => ['condition' => "{$column} >= ?", 'params' => [$value]],
            '<=', 'lte' => ['condition' => "{$column} <= ?", 'params' => [$value]],
            'like' => ['condition' => "{$column} LIKE ?", 'params' => [$value]],
            'not like' => ['condition' => "{$column} NOT LIKE ?", 'params' => [$value]],
            'between' => $this->buildBetweenCondition($column, $value, false),
            'not between' => $this->buildBetweenCondition($column, $value, true),
            default => throw new InvalidArgumentException("Unsupported operator: {$operator}"),
        };
    }

    private function buildBetweenCondition(string $column, mixed $value, bool $not): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException('BETWEEN operator requires an array with exactly 2 values');
        }

        $operator = $not ? 'NOT BETWEEN' : 'BETWEEN';
        return [
            'condition' => "{$column} {$operator} ? AND ?",
            'params' => [$value[0], $value[1]],
        ];
    }

    private function whereGroupCallback(callable $callback, string $operator): self
    {
        $groupBuilder = $this->createSubQueryBuilder();
        $callback($groupBuilder);

        if (!empty($groupBuilder->where)) {
            $this->where[] = [
                'operator' => $operator,
                'condition' => null,
                'params' => [],
                'group' => $groupBuilder->where,
            ];
        }

        return $this;
    }

    public function orWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (is_callable($columnOrCallback)) {
            return $this->whereGroupCallback($columnOrCallback, 'OR');
        }

        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $condition = $this->buildConditionFromOperator($columnOrCallback, $operator, $value);
        $this->where[] = [
            'operator' => 'OR',
            'condition' => $condition['condition'],
            'params' => $condition['params'],
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

    public function apply(CriteriaInterface $criteria): self
    {
        $criteria->apply($this);

        return $this;
    }

    public function whereNot(callable $callback): self
    {
        $groupBuilder = $this->createSubQueryBuilder();
        $callback($groupBuilder);

        if (!empty($groupBuilder->where)) {
            $groupClause = $groupBuilder->sqlCompiler->buildWhereClause($groupBuilder->where);
            $this->where[] = [
                'operator' => 'AND',
                'condition' => "NOT ({$groupClause})",
                'params' => $groupBuilder->sqlCompiler->collectWhereParametersPublic($groupBuilder->where),
                'group' => null,
            ];
        }

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

    public function insert(object|array|string $entitiesOrTable): self
    {
        $this->dmlCommand = 'insert';
        $this->insertColumns = [];
        $this->insertValues = [];

        if (is_string($entitiesOrTable)) {
            if (empty($this->from)) {
                $this->from($entitiesOrTable);
            }
            return $this;
        }

        $entitiesArray = is_array($entitiesOrTable) ? $entitiesOrTable : [$entitiesOrTable];

        if (empty($entitiesArray)) {
            throw new InvalidArgumentException('INSERT requires at least one entity');
        }

        $firstEntity = $entitiesArray[0];
        if (!is_object($firstEntity)) {
            throw new InvalidArgumentException('INSERT entities must be objects');
        }

        $this->dmlEntity = $firstEntity;

        if ($this->entityClass === null) {
            $this->setEntityClass($firstEntity::class);
        }

        if (empty($this->from)) {
            $this->from($this->resolveTableName($firstEntity::class));
        }

        foreach ($entitiesArray as $entity) {
            $data = $this->extractEntityInsertData($entity);
            if (empty($this->insertColumns)) {
                $this->insertColumns = $data['columns'];
            }
            $this->insertValues[] = $data['values'];
        }

        return $this;
    }

    public function values(array $values): self
    {
        if ($this->dmlCommand !== 'insert') {
            throw new InvalidArgumentException('values() can only be used with insert()');
        }

        if (empty($this->insertColumns)) {
            $this->insertColumns = array_keys($values);
        }

        if (count($values) !== count($this->insertColumns)) {
            throw new InvalidArgumentException('Number of values must match number of columns');
        }

        $orderedValues = [];
        foreach ($this->insertColumns as $column) {
            $orderedValues[] = $values[$column];
        }

        $this->insertValues[] = $orderedValues;

        return $this;
    }

    public function update(object|string $entityOrTable): self
    {
        $this->dmlCommand = 'update';
        $this->updateSet = [];

        if (is_object($entityOrTable)) {
            $this->dmlEntity = $entityOrTable;

            if ($this->entityClass === null) {
                $this->setEntityClass($entityOrTable::class);
            }

            if (empty($this->from)) {
                $this->from($this->resolveTableName($entityOrTable::class));
            }

            $whereClause = $this->buildEntityWhereClause($entityOrTable);
            if (!empty($whereClause['clause'])) {
                $this->where($whereClause['clause'], ...$whereClause['values']);
            }
        } else {
            if (empty($this->from)) {
                $this->from($entityOrTable);
            }
        }

        return $this;
    }

    public function set(string|array $column, mixed $value = null): self
    {
        if ($this->dmlCommand !== 'update') {
            throw new InvalidArgumentException('set() can only be used with update()');
        }

        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->updateSet[$col] = $val;
            }
        } else {
            $this->updateSet[$column] = $value;
        }

        return $this;
    }

    public function delete(object|string $entityOrTable): self
    {
        $this->dmlCommand = 'delete';

        if (is_object($entityOrTable)) {
            $this->dmlEntity = $entityOrTable;

            if ($this->entityClass === null) {
                $this->setEntityClass($entityOrTable::class);
            }

            if (empty($this->from)) {
                $this->from($this->resolveTableName($entityOrTable::class));
            }

            $whereClause = $this->buildEntityWhereClause($entityOrTable);
            if (!empty($whereClause['clause'])) {
                $this->where($whereClause['clause'], ...$whereClause['values']);
            }
        } else {
            if (empty($this->from)) {
                $this->from($entityOrTable);
            }
        }

        return $this;
    }

    public function returning(string ...$columns): self
    {
        $this->returning = array_merge($this->returning, $columns);

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
        $this->cursor = null;
        $this->cursorLimit = null;
        $this->distinct = false;
        $this->lockForUpdate = false;
        $this->dmlCommand = null;
        $this->insertColumns = [];
        $this->insertValues = [];
        $this->updateSet = [];
        $this->dmlEntity = null;
        $this->returning = [];

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

    public function cursor(string $token, CursorDirection $direction = CursorDirection::NEXT): self
    {
        if (empty($this->orderBy)) {
            throw new CursorPaginationException('ORDER BY clause is required for cursor pagination');
        }

        if (count($this->orderBy) > 2) {
            throw new CursorPaginationException('Cursor pagination supports maximum 2 ORDER BY columns');
        }

        $decodedCursor = $this->cursorCodec->decode($token);
        $this->cursor = new Cursor($decodedCursor->getValues(), $direction);

        return $this;
    }

    public function cursorLimit(int $limit): self
    {
        $this->cursorLimit = $limit;

        return $this;
    }

    public function groupBy(string ...$fields): self
    {
        $this->groupBy = array_merge($this->groupBy, $fields);

        return $this;
    }

    public function lock(): self
    {
        $this->lockForUpdate = true;

        return $this;
    }

    /**
     * Enable result caching for this query.
     *
     * Note: Locked queries (using lock()) will never be cached, even if caching is enabled.
     * This ensures transactional integrity.
     *
     * @param int $lifetime Cache lifetime in seconds (must be positive)
     * @param string|null $resultCacheId Optional custom cache key. If not provided, key is auto-generated from query characteristics.
     * @return self
     * @throws InvalidArgumentException If cache pool is not configured or lifetime is invalid
     */
    public function enableResultCache(int $lifetime, ?string $resultCacheId = null): self
    {
        $this->resultCache->enable($lifetime, $resultCacheId);

        return $this;
    }

    /**
     * Disable result caching for this query.
     *
     * @return self
     */
    public function disableResultCache(): self
    {
        $this->resultCache->disable();

        return $this;
    }

    public function setHydrator(?HydratorInterface $hydrator): self
    {
        $this->hydrator = $hydrator;
        $this->resultExecutor = new QueryResultExecutor($this->connection, $this->resultCache, $hydrator, $this->unitOfWork);

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
        $this->resultExecutor = new QueryResultExecutor($this->connection, $this->resultCache, $this->hydrator, $unitOfWork);

        return $this;
    }

    public function setEntityClass(string $entityClass): self
    {
        $this->entityClass = $entityClass;
        $this->sqlCompiler->setEntityClass($entityClass);

        // If no FROM clause set yet, try to set it from entity
        if (empty($this->from)) {
            $this->from($this->resolveTableName($entityClass));
        }

        return $this;
    }

    public function setSoftDeleteEnabled(bool $enabled): self
    {
        $this->softDeleteEnabled = $enabled;

        return $this;
    }

    public function withDeleted(): self
    {
        $this->includeDeleted = true;

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function getSQL(): string
    {
        if ($this->rawSql !== null) {
            return $this->rawSql;
        }

        if ($this->dmlCommand === 'insert') {
            if (empty($this->from)) {
                throw new InvalidArgumentException('INSERT requires a table name');
            }

            [$sql] = $this->sqlCompiler->compileInsert(
                $this->from,
                $this->insertColumns,
                $this->insertValues,
                $this->returning
            );

            return $sql;
        }

        if ($this->dmlCommand === 'update') {
            if (empty($this->from)) {
                throw new InvalidArgumentException('UPDATE requires a table name');
            }

            $where = $this->softDeleteFilter->apply($this->where, $this->entityClass, $this->softDeleteEnabled, $this->includeDeleted, $this->rawSql);

            [$sql] = $this->sqlCompiler->compileUpdate(
                $this->from,
                $this->updateSet,
                $where,
                $this->returning
            );

            return $sql;
        }

        if ($this->dmlCommand === 'delete') {
            if (empty($this->from)) {
                throw new InvalidArgumentException('DELETE requires a table name');
            }

            $where = $this->softDeleteFilter->apply($this->where, $this->entityClass, $this->softDeleteEnabled, $this->includeDeleted, $this->rawSql);

            [$sql] = $this->sqlCompiler->compileDelete(
                $this->from,
                $where,
                $this->returning
            );

            return $sql;
        }

        $where = $this->softDeleteFilter->apply($this->where, $this->entityClass, $this->softDeleteEnabled, $this->includeDeleted, $this->rawSql);

        $limitToUse = $this->cursor !== null ? $this->cursorLimit : $this->limit;

        [$sql] = $this->sqlCompiler->compile(
            $this->rawSql,
            $this->rawParams,
            $this->from,
            $this->select,
            $this->joins,
            $where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $limitToUse,
            $this->offset,
            $this->distinct,
            $this->lockForUpdate,
            $this->cursor
        );

        return $sql;
    }

    public function getParameters(): array
    {
        if ($this->rawSql !== null) {
            return $this->rawParams;
        }

        if ($this->dmlCommand === 'insert') {
            [, $params] = $this->sqlCompiler->compileInsert(
                $this->from,
                $this->insertColumns,
                $this->insertValues,
                $this->returning
            );

            return $params;
        }

        if ($this->dmlCommand === 'update') {
            $where = $this->softDeleteFilter->apply($this->where, $this->entityClass, $this->softDeleteEnabled, $this->includeDeleted, $this->rawSql);

            [, $params] = $this->sqlCompiler->compileUpdate(
                $this->from,
                $this->updateSet,
                $where,
                $this->returning
            );

            return $params;
        }

        if ($this->dmlCommand === 'delete') {
            $where = $this->softDeleteFilter->apply($this->where, $this->entityClass, $this->softDeleteEnabled, $this->includeDeleted, $this->rawSql);

            [, $params] = $this->sqlCompiler->compileDelete(
                $this->from,
                $where,
                $this->returning
            );

            return $params;
        }

        $where = $this->softDeleteFilter->apply($this->where, $this->entityClass, $this->softDeleteEnabled, $this->includeDeleted, $this->rawSql);

        $limitToUse = $this->cursor !== null ? $this->cursorLimit : $this->limit;

        [, $params] = $this->sqlCompiler->compile(
            $this->rawSql,
            $this->rawParams,
            $this->from,
            $this->select,
            $this->joins,
            $where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $limitToUse,
            $this->offset,
            $this->distinct,
            $this->lockForUpdate,
            $this->cursor
        );

        return $params;
    }

    public function getResult(?string $entityClass = null): mixed
    {
        $targetClass = $entityClass ?? $this->entityClass;
        $sql = $this->getSQL();
        $params = $this->getParameters();

        return $this->resultExecutor->execute(
            $sql,
            $params,
            $targetClass,
            $this->lockForUpdate,
            $this->distinct,
            $this->limit,
            $this->offset,
            $this->orderBy,
            $this->groupBy,
            $this->having
        );
    }

    public function getSingleResult(?string $entityClass = null): mixed
    {
        $originalLimit = $this->limit;
        $this->limit = 1;
        $results = $this->getResult($entityClass);
        $this->limit = $originalLimit;

        return is_array($results) ? ($results[0] ?? null) : $results;
    }

    public function getCursorPaginatedResult(?string $entityClass = null): CursorPaginator
    {
        $this->cursorPaginationHandler->validateCursorPagination($this->orderBy, $this->cursorLimit);

        $results = $this->getResult($entityClass);
        $items = is_array($results) ? $results : [];

        return $this->cursorPaginationHandler->createPaginatorWithEntityClass(
            $items,
            $this->orderBy,
            $this->cursor,
            $this->cursorLimit,
            $entityClass ?? $this->entityClass
        );
    }

    public function execute(): mixed
    {
        if ($this->lockForUpdate && !$this->connection->inTransaction()) {
            throw new TransactionRequiredException('lock() requires an active transaction');
        }

        $sql = $this->getSQL();
        $params = $this->getParameters();

        $statement = $this->connection->executeQuery($sql, $params);

        if ($this->dmlCommand === 'insert' && !empty($this->returning)) {
            return $statement->fetchAll();
        }

        if ($this->dmlCommand === 'insert' && count($this->insertValues) === 1) {
            $driverName = $this->connection->getDriverName();
            if ($driverName === Connection::PGSQL) {
                if (!empty($this->returning)) {
                    return $statement->fetchAll();
                }
            }

            $lastInsertId = $this->connection->lastInsertId();
            if ($lastInsertId !== false) {
                return $lastInsertId;
            }
        }

        if ($this->dmlCommand === 'update' && !empty($this->returning)) {
            return $statement->fetchAll();
        }

        if ($this->dmlCommand === 'delete' && !empty($this->returning)) {
            return $statement->fetchAll();
        }

        return $statement->rowCount();
    }

    private function resolveTableName(string $entityClass): string
    {
        if ($this->metadataRegistry) {
            return $this->metadataRegistry->getTableName($entityClass);
        }

        // Fallback: simple pluralization: User -> users, Post -> posts
        $className = basename(str_replace('\\', '/', $entityClass));

        return strtolower($className) . 's';
    }


    private function extractEntityInsertData(object $entity): array
    {
        $reflectionEntity = new ReflectionEntity($entity::class);

        $properties = array_filter(
            iterator_to_array($reflectionEntity->getEntityProperties()),
            fn ($property) => $property instanceof ReflectionProperty
        );

        $columns = [];
        $values = [];

        foreach ($properties as $property) {
            $columnName = $property->getColumnName();

            $value = $property->getValue($entity);

            if ($property->isPrimaryKey() && $value === null) {
                continue;
            }

            if ($value === null && !$property->isNullable() && $property->getDefaultValue() === null) {
                continue;
            }

            $columns[] = $columnName;
            $values[] = $value;
        }

        $this->addMorphToColumns($entity, $columns, $columns, $values);

        return ['columns' => $columns, 'values' => $values];
    }

    private function buildEntityWhereClause(object $entity): array
    {
        $reflectionEntity = new ReflectionEntity($entity::class);
        $whereParts = [];
        $whereValues = [];

        $primaryKeyColumns = $reflectionEntity->getPrimaryKeyColumns();
        if (!empty($primaryKeyColumns)) {
            foreach ($primaryKeyColumns as $pkColumn) {
                $pkProperty = null;
                foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                    if ($property->getColumnName() === $pkColumn && $property instanceof ReflectionProperty) {
                        $pkProperty = $property;
                        break;
                    }
                }

                if ($pkProperty === null) {
                    $reflectionEntity = new ReflectionEntity($entity::class);
                    foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                        if ($property instanceof ReflectionProperty && $property->getFieldName() === 'id') {
                            $idValue = $property->getValue($entity);
                            if ($idValue !== null) {
                                $whereParts[] = 'id = ?';
                                $whereValues[] = $idValue;
                            }
                            break;
                        }
                    }
                    continue;
                }

                $pkValue = $pkProperty->getValue($entity);

                $whereParts[] = "{$pkColumn} = ?";
                $whereValues[] = $pkValue;
            }
        } else {
            $reflectionEntity = new ReflectionEntity($entity::class);
            foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                if ($property instanceof ReflectionProperty && $property->getFieldName() === 'id') {
                    $idValue = $property->getValue($entity);
                    if ($idValue !== null) {
                        $whereParts[] = 'id = ?';
                        $whereValues[] = $idValue;
                    }
                    break;
                }
            }
        }

        return ['clause' => implode(' AND ', $whereParts), 'values' => $whereValues];
    }

    private function addMorphToColumns(object $entity, array &$columns, array &$placeholders, array &$values): void
    {
        if ($this->metadataRegistry === null) {
            return;
        }

        try {
            $metadata = $this->metadataRegistry->getMetadata($entity::class);
            $relations = $metadata->getRelations();

            foreach ($relations as $relation) {
                if ($relation->isMorphTo()) {
                    $propertyName = $relation->getPropertyName();
                    $reflectionEntity = new ReflectionEntity($entity::class);
                    $relatedProperty = null;
                    foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                        if ($property instanceof ReflectionProperty && $property->getFieldName() === $propertyName) {
                            $relatedProperty = $property;
                            break;
                        }
                    }
                    if ($relatedProperty === null) {
                        continue;
                    }
                    $relatedEntity = $relatedProperty->getValue($entity);

                    if ($relatedEntity !== null) {
                        $morphType = $relatedEntity::class;
                        $relatedId = $this->extractEntityId($relatedEntity);

                        $columns[] = $relation->getMorphTypeColumnName();
                        $placeholders[] = '?';
                        $values[] = $morphType;

                        $columns[] = $relation->getMorphIdColumnName();
                        $placeholders[] = '?';
                        $values[] = $relatedId;
                    }
                }
            }
        } catch (\InvalidArgumentException $e) {
        }
    }

    private function extractEntityId(object $entity): mixed
    {
        $reflectionEntity = new ReflectionEntity($entity::class);

        foreach (iterator_to_array($reflectionEntity->getEntityFieldsProperties()) as $property) {
            if ($property->isPrimaryKey() && $property instanceof ReflectionProperty) {
                return $property->getValue($entity);
            }
        }

        $reflectionEntity = new ReflectionEntity($entity::class);
        foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
            if ($property instanceof ReflectionProperty && $property->getFieldName() === 'id') {
                return $property->getValue($entity);
            }
        }

        return null;
    }
}
