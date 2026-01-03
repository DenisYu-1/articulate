<?php

namespace Articulate\Modules\Generators;

use Symfony\Component\Uid\Ulid;

/**
 * ULID generator for primary keys.
 * ULIDs are URL-safe, lexicographically sortable identifiers.
 */
class UlidGenerator extends AbstractGenerator {
    public function __construct()
    {
        parent::__construct('ulid');
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        return Ulid::generate();
    }
}
