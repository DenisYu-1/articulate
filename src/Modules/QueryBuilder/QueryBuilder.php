<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;

class QueryBuilder {
    private Connection $connection;

    private ?HydratorInterface $hydrator;

    private ?string $entityClass = null;

    private ?EntityMetadataRegistry $metadataRegistry;

    private string $from = '';

    private array $select = [];

    private array $where = [];

    private array $joins = []; // [['sql' => '...', 'params' => [...]], ...]

    private ?int $limit = null;

    private ?int $offset = null;

    private array $orderBy = [];

    private array $groupBy = [];

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
        $this->where[] = ['condition' => $condition, 'params' => $params];

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
        $sql = 'SELECT ' . (empty($this->select) ? '*' : implode(', ', $this->select));
        $sql .= ' FROM ' . $this->from;

        if (!empty($this->joins)) {
            $joinSqls = array_column($this->joins, 'sql');
            $sql .= ' ' . implode(' ', $joinSqls);
        }

        if (!empty($this->where)) {
            $conditions = array_column($this->where, 'condition');
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
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
        $params = [];

        // Add join parameters first (they appear in SQL before WHERE)
        foreach ($this->joins as $join) {
            $params = array_merge($params, $join['params']);
        }

        // Add WHERE parameters
        foreach ($this->where as $whereClause) {
            $params = array_merge($params, $whereClause['params']);
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
            return array_map(
                fn ($row) => $this->hydrator->hydrate($targetClass, $row),
                $rawResults
            );
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
            } catch (\Exception $e) {
                // Fall back to simple pluralization if metadata fails
            }
        }

        // Fallback: simple pluralization: User -> users, Post -> posts
        $className = basename(str_replace('\\', '/', $entityClass));

        return strtolower($className) . 's';
    }
}
