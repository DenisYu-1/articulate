<?php

namespace Articulate\Modules\EntityManager;

class ArrayHydrator implements HydratorInterface
{
    public function hydrate(string $class, array $data, ?object $entity = null): mixed
    {
        // ArrayHydrator returns associative arrays instead of objects
        return $data;
    }

    public function extract(mixed $entity): array
    {
        // If entity is already an array, return it
        if (is_array($entity)) {
            return $entity;
        }

        // Otherwise, this hydrator is for read-only operations
        throw new \RuntimeException('ArrayHydrator is for read-only operations');
    }

    public function hydratePartial(object $entity, array $data): void
    {
        // Not applicable for array hydration
        throw new \RuntimeException('Partial hydration not supported for arrays');
    }
}
