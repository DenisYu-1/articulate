<?php

namespace Articulate\Modules\Generators;

use InvalidArgumentException;

/**
 * Registry for managing ID generators.
 */
class GeneratorRegistry {
    /**
     * @var GeneratorInterface[]
     */
    private array $generators = [];

    /**
     * @var GeneratorInterface
     */
    private GeneratorInterface $defaultGenerator;

    /**
     * @var GeneratorStrategyInterface[]
     */
    private array $strategies = [];

    public function __construct()
    {
        // Register default generators
        $this->register(new AutoIncrementGenerator());
        $this->register(new UuidGenerator());
        $this->register(new SerialGenerator());
        $this->register(new UlidGenerator());
        $this->register(new UuidV7Generator());

        // Set auto_increment as default for backward compatibility
        $this->defaultGenerator = $this->generators['auto_increment'];
    }

    /**
     * Register a generator.
     *
     * @param GeneratorInterface $generator
     */
    public function register(GeneratorInterface $generator): void
    {
        $this->generators[$generator->getType()] = $generator;
    }

    /**
     * Get a generator by type.
     *
     * @param string $type
     * @return GeneratorInterface
     * @throws InvalidArgumentException
     */
    public function getGenerator(string $type): GeneratorInterface
    {
        if (!isset($this->generators[$type])) {
            throw new InvalidArgumentException("Generator type '{$type}' not found");
        }

        return $this->generators[$type];
    }

    /**
     * Get the default generator.
     *
     * @return GeneratorInterface
     */
    public function getDefaultGenerator(): GeneratorInterface
    {
        return $this->defaultGenerator;
    }

    /**
     * Set the default generator.
     *
     * @param string $type
     * @throws InvalidArgumentException
     */
    public function setDefaultGenerator(string $type): void
    {
        $this->defaultGenerator = $this->getGenerator($type);
    }

    /**
     * Check if a generator type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function hasGenerator(string $type): bool
    {
        return isset($this->generators[$type]);
    }

    /**
     * Get all registered generator types.
     *
     * @return string[]
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->generators);
    }

    /**
     * Add a custom strategy.
     *
     * @param GeneratorStrategyInterface $strategy
     */
    public function addStrategy(GeneratorStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    /**
     * Get all registered strategies.
     *
     * @return GeneratorStrategyInterface[]
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Find a strategy that supports the given generator type.
     *
     * @param string $generatorType
     * @return GeneratorStrategyInterface|null
     */
    public function findStrategy(string $generatorType): ?GeneratorStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($generatorType)) {
                return $strategy;
            }
        }

        return null;
    }
}
