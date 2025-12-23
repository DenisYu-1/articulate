<?php

namespace Articulate\Modules\EntityManager;

interface HydratorInterface
{
    /**
     * Hydrate a database row into an entity object
     *
     * @param string $class The entity class name
     * @param array $data Database row data
     * @param object|null $entity Existing entity to hydrate into (optional)
     * @return mixed The hydrated entity (object, array, or scalar depending on hydrator)
     */
    public function hydrate(string $class, array $data, ?object $entity = null): mixed;

    /**
     * Extract entity data for database storage
     *
     * @param mixed $entity The entity to extract (object or array depending on hydrator)
     * @return array Database-ready data
     */
    public function extract(mixed $entity): array;

    /**
     * Partially hydrate data into an existing entity
     *
     * @param object $entity The entity to hydrate into
     * @param array $data Partial data to hydrate
     */
    public function hydratePartial(object $entity, array $data): void;
}
