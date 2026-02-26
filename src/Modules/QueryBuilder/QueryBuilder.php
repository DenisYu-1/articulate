<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\CursorPaginationException;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\QueryBuilder\Filter\FilterCollection;
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

    private WhereClauseBuilder $whereBuilder;

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

    private FilterCollection $filters;

    /** @var string[] */
    private array $disabledFilters = [];

    private DmlOperationHandler $dmlHandler;

    public function __construct(
        Connection $connection,
        ?HydratorInterface $hydrator = null,
        ?EntityMetadataRegistry $metadataRegistry = null,
        ?CacheItemPoolInterface $resultCache = null,
        ?FilterCollection $filters = null
    ) {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
        $this->entityClass = null;
        $this->metadataRegistry = $metadataRegistry;
        $this->resultCache = new QueryResultCache($resultCache);
        $this->sqlCompiler = new SqlCompiler($metadataRegistry);
        $this->cursorCodec = new CursorCodec();
        $this->filters = $filters ?? new FilterCollection();
        $this->cursorPaginationHandler = new CursorPaginationHandler($this->cursorCodec, $metadataRegistry);
        $this->resultExecutor = new QueryResultExecutor($connection, $this->resultCache, $hydrator, null);
        $this->dmlHandler = new DmlOperationHandler($metadataRegistry);
        $this->whereBuilder = new WhereClauseBuilder(
            fn () => $this->createSubQueryBuilder(),
            $this->sqlCompiler
        );
    }

    public function createSubQueryBuilder(): QueryBuilder
    {
        $subBuilder = new self($this->connection, $this->hydrator, $this->metadataRegistry, null, $this->filters);
        $subBuilder->sqlCompiler->setEntityClass($this->entityClass);
        $subBuilder->disabledFilters = $this->disabledFilters;

        return $subBuilder;
    }

    public function withoutFilter(string $name): self
    {
        if (!in_array($name, $this->disabledFilters, true)) {
            $this->disabledFilters[] = $name;
        }

        return $this;
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
        $this->whereBuilder->where($columnOrCallback, $operatorOrValue, $value);

        return $this;
    }

    public function orWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        $this->whereBuilder->orWhere($columnOrCallback, $operatorOrValue, $value);

        return $this;
    }

    public function whereIn(string $column, array|QueryBuilder $values): self
    {
        $this->whereBuilder->whereIn($column, $values);

        return $this;
    }

    public function whereNotIn(string $column, array|QueryBuilder $values): self
    {
        $this->whereBuilder->whereNotIn($column, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->whereBuilder->whereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->whereBuilder->whereNotNull($column);

        return $this;
    }

    public function whereExists(QueryBuilder $subquery): self
    {
        $this->whereBuilder->whereExists($subquery);

        return $this;
    }

    public function apply(CriteriaInterface $criteria): self
    {
        $criteria->apply($this);

        return $this;
    }

    public function whereNot(callable $callback): self
    {
        $this->whereBuilder->whereNot($callback);

        return $this;
    }

    public function orWhereExists(QueryBuilder $subquery): self
    {
        $this->whereBuilder->orWhereExists($subquery);

        return $this;
    }

    public function getWhereConditions(): array
    {
        return $this->whereBuilder->getConditions();
    }

    public function insert(object|array|string $entitiesOrTable): self
    {
        $this->dmlHandler->insert($entitiesOrTable, $this->createDmlContext());

        return $this;
    }

    public function values(array $values): self
    {
        $this->dmlHandler->values($values);

        return $this;
    }

    public function update(object|string $entityOrTable): self
    {
        $this->dmlHandler->update($entityOrTable, $this->createDmlContext());

        return $this;
    }

    public function set(string|array $column, mixed $value = null): self
    {
        $this->dmlHandler->set($column, $value);

        return $this;
    }

    public function delete(object|string $entityOrTable): self
    {
        $this->dmlHandler->delete($entityOrTable, $this->createDmlContext());

        return $this;
    }

    public function returning(string ...$columns): self
    {
        $this->dmlHandler->returning(...$columns);

        return $this;
    }

    private function createDmlContext(): DmlContext
    {
        return new class($this) implements DmlContext {
            public function __construct(private readonly QueryBuilder $qb)
            {
            }

            public function getEntityClass(): ?string
            {
                return $this->qb->getEntityClass();
            }

            public function getFrom(): string
            {
                return $this->qb->getFrom();
            }

            public function setEntityClass(string $entityClass): void
            {
                $this->qb->setEntityClass($entityClass);
            }

            public function setFrom(string $from): void
            {
                $this->qb->setFromTable($from);
            }

            public function addWhere(string $clause, mixed ...$values): void
            {
                $this->qb->addWhereCondition($clause, ...$values);
            }
        };
    }

    // Reset methods
    public function resetWhere(): self
    {
        $this->whereBuilder->reset();

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
        $this->whereBuilder->reset();
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
        $this->dmlHandler->reset();

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
        $this->whereBuilder->whereRaw($condition, ...$params);

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

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function setFromTable(string $table): void
    {
        $this->from = $table;
    }

    public function addWhereCondition(string $condition, mixed ...$params): void
    {
        $actualParams = count($params) === 1 && is_array($params[0]) ? $params[0] : $params;

        $this->whereBuilder->addCondition($condition, $actualParams, 'AND');
    }

    private function getFilteredWhere(): array
    {
        $where = $this->whereBuilder->getConditions();

        if ($this->entityClass !== null && $this->rawSql === null && $this->metadataRegistry !== null) {
            try {
                $metadata = $this->metadataRegistry->getMetadata($this->entityClass);
                foreach ($this->filters->getActiveConditions($metadata) as $name => $condition) {
                    if (!in_array($name, $this->disabledFilters, true)) {
                        $where[] = ['operator' => 'AND', 'condition' => $condition, 'params' => [], 'group' => null];
                    }
                }
            } catch (InvalidArgumentException $e) {
            }
        }

        return $where;
    }

    /** @return array{0: string, 1: array} */
    private function build(): array
    {
        if ($this->rawSql !== null) {
            return [$this->rawSql, $this->rawParams];
        }

        $dmlCommand = $this->dmlHandler->getCommand();

        if ($dmlCommand === 'insert') {
            if (empty($this->from)) {
                throw new InvalidArgumentException('INSERT requires a table name');
            }

            return $this->sqlCompiler->compileInsert(
                $this->from,
                $this->dmlHandler->getInsertColumns(),
                $this->dmlHandler->getInsertValues(),
                $this->dmlHandler->getReturning()
            );
        }

        $where = $this->getFilteredWhere();

        if ($dmlCommand === 'update') {
            if (empty($this->from)) {
                throw new InvalidArgumentException('UPDATE requires a table name');
            }

            return $this->sqlCompiler->compileUpdate(
                $this->from,
                $this->dmlHandler->getUpdateSet(),
                $where,
                $this->dmlHandler->getReturning()
            );
        }

        if ($dmlCommand === 'delete') {
            if (empty($this->from)) {
                throw new InvalidArgumentException('DELETE requires a table name');
            }

            return $this->sqlCompiler->compileDelete(
                $this->from,
                $where,
                $this->dmlHandler->getReturning()
            );
        }

        $limitToUse = $this->cursorLimit !== null ? $this->cursorLimit : $this->limit;

        return $this->sqlCompiler->compile(
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
    }

    public function getSQL(): string
    {
        return $this->build()[0];
    }

    public function getParameters(): array
    {
        return $this->build()[1];
    }

    public function getResult(?string $entityClass = null): mixed
    {
        $targetClass = $entityClass ?? $this->entityClass;

        // Don't hydrate when using aggregate functions
        if ($this->hasAggregateFunction()) {
            $targetClass = null;
        }

        // Don't hydrate when selecting specific columns (not SELECT *)
        if ($this->hasSpecificColumnSelection()) {
            $targetClass = null;
        }

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
        $dmlCommand = $this->dmlHandler->getCommand();

        if ($dmlCommand !== null) {
            return $this->dmlHandler->execute(
                $this->connection,
                $this->sqlCompiler,
                $this->getFilteredWhere(),
                $this->from,
                $this->lockForUpdate,
                fn (string $sql, array $params) => $this->expandInPlaceholders($sql, $params)
            );
        }

        if ($this->lockForUpdate && !$this->connection->inTransaction()) {
            throw new TransactionRequiredException('lock() requires an active transaction');
        }

        $sql = $this->getSQL();
        $params = $this->getParameters();
        [$sql, $params] = $this->expandInPlaceholders($sql, $params);

        return $this->connection->executeQuery($sql, $params)->rowCount();
    }

    private function resolveTableName(string $entityClass): string
    {
        if ($this->metadataRegistry) {
            return $this->metadataRegistry->getTableName($entityClass);
        }

        $className = basename(str_replace('\\', '/', $entityClass));

        return strtolower($className) . 's';
    }

    /**
     * Expand IN (?) placeholders for array parameters.
     * Converts "col IN (?)" with [[1,2,3]] to "col IN (?,?,?)" with [1,2,3].
     */
    private function expandInPlaceholders(string $sql, array $params): array
    {
        $expandedParams = [];
        $paramIndex = 0;

        // Process each parameter and expand if it's an array for IN clause
        $newSql = preg_replace_callback('/\?/', function ($match) use ($params, &$paramIndex, &$expandedParams) {
            if (!isset($params[$paramIndex])) {
                return '?';
            }

            $param = $params[$paramIndex];
            $paramIndex++;

            // If parameter is an array, expand the placeholder
            if (is_array($param)) {
                $count = count($param);
                if ($count === 0) {
                    // Empty array - should not happen in normal usage
                    $expandedParams[] = null;

                    return '?';
                }

                // Add each array element as a separate parameter
                foreach ($param as $value) {
                    $expandedParams[] = $value;
                }

                // Create multiple placeholders
                return implode(',', array_fill(0, $count, '?'));
            }

            // Non-array parameter, keep as-is
            $expandedParams[] = $param;

            return '?';
        }, $sql);

        return [$newSql, $expandedParams];
    }

    private function hasAggregateFunction(): bool
    {
        foreach ($this->select as $selectItem) {
            if (preg_match('/\b(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT)\s*\(/i', $selectItem)) {
                return true;
            }
        }

        return false;
    }

    private function hasSpecificColumnSelection(): bool
    {
        // If select is empty, SQL compiler will use SELECT *
        if (empty($this->select)) {
            return false;
        }

        // If select contains only '*', it's not specific
        if (count($this->select) === 1 && $this->select[0] === '*') {
            return false;
        }

        // Otherwise, we have specific column selection
        return true;
    }
}
