<?php

namespace Articulate\Modules\Repository;

use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\QueryBuilder;

abstract class AbstractRepository implements RepositoryInterface {
    protected EntityManager $entityManager;

    protected string $entityClass;

    public function __construct(EntityManager $entityManager, string $entityClass)
    {
        $this->entityManager = $entityManager;
        $this->entityClass = $entityClass;
    }

    public function find(mixed $id): ?object
    {
        return $this->entityManager->find($this->entityClass, $id);
    }

    public function findAll(): array
    {
        return $this->entityManager->findAll($this->entityClass);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return object[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder();

        // Apply criteria
        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->where("{$field} IN (?)", $value);
            } else {
                $qb->where("{$field} = ?", $value);
            }
        }

        // Apply ordering
        if ($orderBy !== null) {
            foreach ($orderBy as $field => $direction) {
                $qb->orderBy($field, $direction);
            }
        }

        // Apply limit and offset
        if ($limit !== null) {
            $qb->limit($limit);
        }
        if ($offset !== null) {
            $qb->offset($offset);
        }

        return $qb->getResult();
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return object|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $results = $this->findBy($criteria, $orderBy, 1);

        return $results[0] ?? null;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder();

        // Apply criteria
        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->where("{$field} IN (?)", $value);
            } else {
                $qb->where("{$field} = ?", $value);
            }
        }

        $qb->count();

        return $qb->getSingleResult() ?? 0;
    }

    public function exists(mixed $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Create a new QueryBuilder instance pre-configured for this entity.
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder($this->entityClass);
    }

    /**
     * Persist an entity.
     */
    protected function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    /**
     * Remove an entity.
     */
    protected function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    /**
     * Flush changes to the database.
     */
    protected function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Get the entity class name.
     */
    protected function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
}
