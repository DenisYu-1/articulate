<?php

namespace Articulate\Modules\Generators;

/**
 * Auto-increment generator for integer primary keys.
 */
class AutoIncrementGenerator extends AbstractGenerator
{
    /**
     * @var int[]
     */
    private array $sequences = [];

    public function __construct()
    {
        parent::__construct('auto_increment');
    }

    public function generate(string $entityClass): mixed
    {
        if (!isset($this->sequences[$entityClass])) {
            $this->sequences[$entityClass] = 0;
        }

        return ++$this->sequences[$entityClass];
    }
}
