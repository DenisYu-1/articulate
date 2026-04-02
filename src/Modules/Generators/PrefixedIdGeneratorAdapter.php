<?php

namespace Articulate\Modules\Generators;

use Articulate\Modules\Generators\Strategies\PrefixedIdGenerator;

class PrefixedIdGeneratorAdapter extends AbstractGenerator {
    private PrefixedIdGenerator $strategy;

    public function __construct()
    {
        parent::__construct('prefixed_id');
        $this->strategy = new PrefixedIdGenerator();
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        return $this->strategy->generate($entityClass, $options);
    }
}
