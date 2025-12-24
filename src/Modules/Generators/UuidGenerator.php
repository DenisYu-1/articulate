<?php

namespace Articulate\Modules\Generators;

use Ramsey\Uuid\Uuid;

/**
 * UUID v4 generator for primary keys
 */
class UuidGenerator extends AbstractGenerator
{
    public function __construct()
    {
        parent::__construct('uuid');
    }

    public function generate(string $entityClass): mixed
    {
        return Uuid::uuid4()->toString();
    }
}
