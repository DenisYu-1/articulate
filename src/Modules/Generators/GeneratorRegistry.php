<?php

namespace Articulate\Modules\Generators;

use InvalidArgumentException;

/**
 * Registry for managing ID generators
 */
class GeneratorRegistry
{
    /**
     * @var GeneratorInterface[]
     */
    private array $generators = [];

    /**
     * @var GeneratorInterface
     */
    private GeneratorInterface $defaultGenerator;

    public function __construct()
    {
        // Register default generators
        $this->register(new AutoIncrementGenerator());
        $this->register(new UuidGenerator());

        // Set auto_increment as default for backward compatibility
        $this->defaultGenerator = $this->generators['auto_increment'];
    }

    /**
     * Register a generator
     *
     * @param GeneratorInterface $generator
     */
    public function register(GeneratorInterface $generator): void
    {
        $this->generators[$generator->getType()] = $generator;
    }

    /**
     * Get a generator by type
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
     * Get the default generator
     *
     * @return GeneratorInterface
     */
    public function getDefaultGenerator(): GeneratorInterface
    {
        return $this->defaultGenerator;
    }

    /**
     * Set the default generator
     *
     * @param string $type
     * @throws InvalidArgumentException
     */
    public function setDefaultGenerator(string $type): void
    {
        $this->defaultGenerator = $this->getGenerator($type);
    }

    /**
     * Check if a generator type is registered
     *
     * @param string $type
     * @return bool
     */
    public function hasGenerator(string $type): bool
    {
        return isset($this->generators[$type]);
    }

    /**
     * Get all registered generator types
     *
     * @return string[]
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->generators);
    }
}
