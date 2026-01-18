<?php

namespace Articulate\Modules\Repository;

use Articulate\Modules\EntityManager\EntityManager;

/**
 * Generic repository implementation for entities.
 *
 * This repository provides basic CRUD operations and can be used for any entity
 * when no custom repository class is specified in the Entity attribute.
 */
class EntityRepository extends AbstractRepository {
    public function __construct(EntityManager $entityManager, string $entityClass)
    {
        parent::__construct($entityManager, $entityClass);
    }
}
