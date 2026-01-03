<?php

namespace Articulate\Modules\Generators;

use Symfony\Component\Uid\UuidV7;

/**
 * UUID v7 generator for primary keys.
 * UUID v7 includes timestamp information for better indexing.
 */
class UuidV7Generator extends AbstractGenerator {
    public function __construct()
    {
        parent::__construct('uuid_v7');
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        return UuidV7::generate();
    }
}
