<?php

namespace Articulate\Modules\EntityManager;

class PartialHydrator implements HydratorInterface {
    public function __construct(
        private readonly ObjectHydrator $objectHydrator
    ) {
    }

    public function hydrate(string $class, array $data, ?object $entity = null): mixed
    {
        // Create entity if not provided
        $entity ??= $this->objectHydrator->hydrate($class, [], null);

        // Only hydrate the provided fields
        $this->objectHydrator->hydratePartial($entity, $data);

        return $entity;
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
