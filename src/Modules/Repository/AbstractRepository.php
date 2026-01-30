<?php

namespace Articulate\Modules\Repository;

use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\CursorDirection;
use Articulate\Modules\QueryBuilder\CursorPaginator;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\Criteria\CriteriaInterface;

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
     * Find entities by criteria object.
     */
    public function findByCriteria(CriteriaInterface $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder();

        // Apply criteria
        $qb->apply($criteria);

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
     * Find one entity by criteria object.
     */
    public function findOneByCriteria(CriteriaInterface $criteria, ?array $orderBy = null): ?object
    {
        $results = $this->findByCriteria($criteria, $orderBy, 1);

        return $results[0] ?? null;
    }

    /**
     * Count entities by criteria object.
     */
    public function countByCriteria(CriteriaInterface $criteria): int
    {
        $qb = $this->createQueryBuilder();

        // Apply criteria
        $qb->apply($criteria);

        $qb->count();

        return $qb->getSingleResult() ?? 0;
    }

    /**
     * Check if any entity exists by criteria object.
     */
    public function existsByCriteria(CriteriaInterface $criteria): bool
    {
        return $this->countByCriteria($criteria) > 0;
    }

    public function findWithCursor(
        ?string $cursor = null,
        int $limit = 20,
        ?array $orderBy = null
    ): CursorPaginator {
        $qb = $this->createQueryBuilder();

        if ($orderBy !== null) {
            foreach ($orderBy as $field => $direction) {
                $qb->orderBy($field, $direction);
            }
        }

        if ($cursor !== null) {
            $qb->cursor($cursor, CursorDirection::NEXT);
        }

        $qb->cursorLimit($limit);

        return $qb->getCursorPaginatedResult();
    }

    public function findWithCursorByCriteria(
        CriteriaInterface $criteria,
        ?string $cursor = null,
        int $limit = 20,
        ?array $orderBy = null
    ): CursorPaginator {
        $qb = $this->createQueryBuilder();

        $qb->apply($criteria);

        if ($orderBy !== null) {
            foreach ($orderBy as $field => $direction) {
                $qb->orderBy($field, $direction);
            }
        }

        if ($cursor !== null) {
            $qb->cursor($cursor, CursorDirection::NEXT);
        }

        $qb->cursorLimit($limit);

        return $qb->getCursorPaginatedResult();
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
