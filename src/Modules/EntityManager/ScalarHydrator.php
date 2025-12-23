<?php

namespace Articulate\Modules\EntityManager;

class ScalarHydrator implements HydratorInterface
{
    public function hydrate(string $class, array $data, ?object $entity = null): mixed
    {
        // ScalarHydrator returns single scalar values
        if (count($data) === 1) {
            return reset($data);
        }

        throw new \RuntimeException('ScalarHydrator expects exactly one column in result');
    }

    public function extract(mixed $entity): array
    {
        // Not applicable for scalar hydration
        throw new \RuntimeException('ScalarHydrator is for read-only operations');
    }

    public function hydratePartial(object $entity, array $data): void
    {
        // Not applicable for scalar hydration
        throw new \RuntimeException('Partial hydration not supported for scalars');
    }
}
