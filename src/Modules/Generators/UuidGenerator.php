<?php

namespace Articulate\Modules\Generators;

use Symfony\Component\Uid\Uuid;

/**
 * UUID v4 generator for primary keys.
 */
class UuidGenerator extends AbstractGenerator {
    public function __construct()
    {
        parent::__construct('uuid_v4');
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        return Uuid::v4()->toRfc4122();
    }
}
