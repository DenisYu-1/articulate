<?php

namespace Articulate\Modules\Generators;

/**
 * Generator that uses custom strategies for ID generation.
 * Allows extensibility through the strategy pattern.
 */
class CustomGenerator extends AbstractGenerator {
    /**
     * @var GeneratorStrategyInterface[]
     */
    private array $strategies = [];

    /**
     * @var array<string, int>
     */
    private array $sequences = [];

    public function __construct()
    {
        parent::__construct('custom');
    }

    /**
     * Add a custom strategy.
     */
    public function addStrategy(GeneratorStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        $generatorType = $options['generator'] ?? 'auto_increment';

        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($generatorType)) {
                return $strategy->generate($entityClass, $options);
            }
        }

        // Fallback to auto increment if no strategy found
        return $this->generateFallback($entityClass);
    }

    private function generateFallback(string $entityClass): int
    {
        if (!isset($this->sequences[$entityClass])) {
            $this->sequences[$entityClass] = 0;
        }

        return ++$this->sequences[$entityClass];
    }
}
