<?php

namespace Articulate\Modules\Repository;

use Articulate\Modules\Repository\Criteria\CriteriaInterface;

interface RepositoryInterface {
    public function find(mixed $id): ?object;

    public function findAll(): array;

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return object[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return object|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;

    public function exists(mixed $id): bool;

    /**
     * Find entities by criteria object.
     *
     * @param CriteriaInterface $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return object[]
     */
    public function findByCriteria(CriteriaInterface $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Find one entity by criteria object.
     *
     * @param CriteriaInterface $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return object|null
     */
    public function findOneByCriteria(CriteriaInterface $criteria, ?array $orderBy = null): ?object;

    /**
     * Count entities by criteria object.
     */
    public function countByCriteria(CriteriaInterface $criteria): int;

    /**
     * Check if any entity exists by criteria object.
     */
    public function existsByCriteria(CriteriaInterface $criteria): bool;
}
