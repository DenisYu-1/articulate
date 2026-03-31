<?php

namespace Articulate\Schema;

interface EntityRegistrarInterface {
    public function registerManaged(object $entity, array $rawData): void;
}
