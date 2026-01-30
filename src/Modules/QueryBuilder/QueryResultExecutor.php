<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;

class QueryResultExecutor
{
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
}
