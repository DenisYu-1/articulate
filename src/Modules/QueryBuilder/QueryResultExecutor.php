<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\EntityManager\UnitOfWorkRegistry;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Schema\HydratorInterface;

class QueryResultExecutor {
    public function __construct(
        private readonly Connection $connection,
        private readonly QueryResultCache $resultCache,
        private readonly ?HydratorInterface $hydrator = null,
        private readonly ?UnitOfWorkRegistry $unitOfWorkRegistry = null,
        private readonly ?UnitOfWork $unitOfWork = null,
        private readonly ?EntityMetadataRegistry $metadataRegistry = null,
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

        [$sql, $params] = $this->expandInPlaceholders($sql, $params);

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
            $cacheKey ??= $this->resultCache->getCacheId() ?? $this->resultCache->generateCacheKey(
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
            $metadata = null;
            if (($this->unitOfWorkRegistry !== null || $this->unitOfWork !== null) && $this->metadataRegistry !== null) {
                try {
                    $metadata = $this->metadataRegistry->getMetadata($entityClass);
                } catch (\InvalidArgumentException) {
                    $metadata = null;
                }
            }

            $entities = [];

            foreach ($rawResults as $row) {
                if ($metadata !== null) {
                    $managedEntity = $this->getManagedEntity($entityClass, $metadata, $row);
                    if ($managedEntity !== null) {
                        $entities[] = $managedEntity;

                        continue;
                    }
                }

                $entity = $this->hydrator->hydrate($entityClass, $row);

                if (is_object($entity)) {
                    if ($this->unitOfWorkRegistry !== null) {
                        $this->unitOfWorkRegistry->active()->registerManaged($entity, $row);
                    } elseif ($this->unitOfWork !== null) {
                        $this->unitOfWork->registerManaged($entity, $row);
                    }
                }

                $entities[] = $entity;
            }

            return $entities;
        }

        return $rawResults;
    }

    private function getManagedEntity(string $entityClass, EntityMetadata $metadata, array $row): ?object
    {
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();

        if (empty($primaryKeyColumns)) {
            return null;
        }

        $id = [];
        foreach ($primaryKeyColumns as $columnName) {
            if (!array_key_exists($columnName, $row)) {
                return null;
            }

            $id[$columnName] = $row[$columnName];
        }

        if (count($id) === 1) {
            $id = array_values($id)[0];
        }

        if ($this->unitOfWorkRegistry !== null) {
            foreach ($this->unitOfWorkRegistry->all() as $unitOfWork) {
                $entity = $unitOfWork->tryGetById($entityClass, $id);
                if ($entity !== null) {
                    return $entity;
                }
            }

            return null;
        }

        return $this->unitOfWork?->tryGetById($entityClass, $id);
    }

    private function expandInPlaceholders(string $sql, array $params): array
    {
        return PlaceholderExpander::expand($sql, $params);
    }
}
