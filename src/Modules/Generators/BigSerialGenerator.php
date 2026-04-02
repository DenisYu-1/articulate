<?php

namespace Articulate\Modules\Generators;

class BigSerialGenerator extends AbstractGenerator {
    /** @var int[] */
    private array $sequences = [];

    public function __construct()
    {
        parent::__construct('bigserial');
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        if (!isset($this->sequences[$entityClass])) {
            $this->sequences[$entityClass] = 0;
        }

        return ++$this->sequences[$entityClass];
    }
}
