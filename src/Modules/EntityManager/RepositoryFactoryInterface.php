<?php

namespace Articulate\Modules\EntityManager;

interface RepositoryFactoryInterface {
    public function getRepository(string $entityClass): object;
}
