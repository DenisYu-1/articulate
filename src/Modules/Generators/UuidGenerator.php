<?php

namespace Articulate\Modules\Generators;

use Symfony\Component\Uid\UuidV4;

class UuidGenerator extends AbstractGenerator {
    public function __construct()
    {
        parent::__construct('uuid_v4');
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        return UuidV4::generate();
    }
}
