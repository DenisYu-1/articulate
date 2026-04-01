<?php

namespace Articulate\Modules\Generators;

/**
 * Generator that uses custom strategies for ID generation.
 * Allows extensibility through the strategy pattern.
 */
class CustomGenerator extends AbstractGenerator {
    /** @var GeneratorStrategyInterface[] */
    private array $strategies = [];

    private array $entityCounters = [];

    public function __construct()
    {
        parent::__construct('custom');
    }

    public function addStrategy(GeneratorStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    protected function generateInternal(string $entityClass, array $options = []): mixed
    {
        $requestedType = $options['generator'] ?? null;

        foreach ($this->strategies as $strategy) {
            if ($requestedType === null || $strategy->supports($requestedType)) {
                return $strategy->generate($entityClass, $options);
            }
        }

        $this->entityCounters[$entityClass] = ($this->entityCounters[$entityClass] ?? 0) + 1;

        return $this->entityCounters[$entityClass];
    }
}
