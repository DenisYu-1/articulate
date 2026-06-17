<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Schema\EntityRegistrarInterface;

final class ActiveUnitOfWorkRegistrar implements EntityRegistrarInterface {
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    public function registerManaged(object $entity, array $rawData): void
    {
        $this->entityManager->getActiveUnitOfWork()->registerManaged($entity, $rawData);
    }
}
