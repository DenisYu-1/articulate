<?php

namespace Articulate\Schema;

interface ManagedEntityStoreInterface extends EntityRegistrarInterface {
    public function tryGetById(string $class, mixed $id): ?object;
}
