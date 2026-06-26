<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Schema\HydratorInterface;

class PartialHydrator implements HydratorInterface {
    public function __construct(
        private readonly ObjectHydrator $objectHydrator
    ) {
    }

    public function hydrate(string $class, array $data, ?object $entity = null, array $with = []): mixed
    {
        // Delegate to ObjectHydrator with the actual data so the entity is registered
        // with its real primary key rather than an empty placeholder.
        return $this->objectHydrator->hydrate($class, $data, $entity, $with);
    }

    public function extract(mixed $entity): array
    {
        // Partial extraction not typically needed
        return $this->objectHydrator->extract($entity);
    }

    public function hydratePartial(object $entity, array $data): void
    {
        $this->objectHydrator->hydratePartial($entity, $data);
    }
}
