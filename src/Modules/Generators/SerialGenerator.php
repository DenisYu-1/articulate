<?php

namespace Articulate\Modules\Generators;

/**
 * Serial generator for databases that support sequences (PostgreSQL, etc.).
 * Uses database sequences for ID generation.
 */
class SerialGenerator extends AbstractGenerator {
    /**
     * @var int[]
     */
    private array $sequences = [];

    public function __construct()
    {
        parent::__construct('serial');
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        // For now, use in-memory sequence simulation
        // In a real implementation, this would use database sequences
        if (!isset($this->sequences[$entityClass])) {
            $this->sequences[$entityClass] = 0;
        }

        return ++$this->sequences[$entityClass];
    }
}
