<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;

class QueryResultExecutor {
    public function __construct(
        private readonly Connection $connection,
        private readonly QueryResultCache $resultCache,
        private readonly ?HydratorInterface $hydrator = null,
        private readonly ?UnitOfWork $unitOfWork = null
    ) {
    }

    public function execute(
        string $sql,
        array $params,
        ?string $entityClass,
        bool $lockForUpdate,
        bool $distinct,
        ?int $limit,
        ?int $offset,
        array $orderBy,
        array $groupBy,
        array $having
    ): mixed {
        if ($lockForUpdate && !$this->connection->inTransaction()) {
            throw new TransactionRequiredException('lock() requires an active transaction');
        }

        if (!$lockForUpdate && $this->resultCache->isEnabled()) {
            $cacheKey = $this->resultCache->getCacheId() ?? $this->resultCache->generateCacheKey(
                $entityClass,
                $sql,
                $params,
                $distinct,
                $limit,
                $offset,
                $orderBy,
                $groupBy,
                $having
            );

            $rawResults = $this->resultCache->get($cacheKey);
            if ($rawResults !== null) {
                if (empty($rawResults)) {
                    return [];
                }

                return $this->hydrateResults($rawResults, $entityClass);
            }
        }

        // Expand IN (?) placeholders for array parameters
        [$sql, $params] = $this->expandInPlaceholders($sql, $params);

        $statement = $this->connection->executeQuery($sql, $params);
        $rawResults = $statement->fetchAll();

        if (!$lockForUpdate && $this->resultCache->isEnabled()) {
            $cacheKey = $this->resultCache->getCacheId() ?? $this->resultCache->generateCacheKey(
                $entityClass,
                $sql,
                $params,
                $distinct,
                $limit,
                $offset,
                $orderBy,
                $groupBy,
                $having
            );

            $this->resultCache->set($cacheKey, $rawResults);
        }

        if (empty($rawResults)) {
            return [];
        }

        return $this->hydrateResults($rawResults, $entityClass);
    }

    private function hydrateResults(array $rawResults, ?string $entityClass): mixed
    {
        if ($entityClass && $this->hydrator) {
            $entities = array_map(
                fn ($row) => $this->hydrator->hydrate($entityClass, $row),
                $rawResults
            );

            if ($this->unitOfWork) {
                foreach ($entities as $entity) {
                    $this->unitOfWork->registerManaged($entity, []);
                }
            }

            return $entities;
        }

        return $rawResults;
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
}
