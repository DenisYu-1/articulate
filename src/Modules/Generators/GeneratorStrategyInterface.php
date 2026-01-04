<?php

namespace Articulate\Modules\Generators;

/**
 * Interface for custom generator strategies.
 * Allows users to implement their own ID generation strategies.
 */
interface GeneratorStrategyInterface {
    /**
     * Generate a new ID value.
     *
     * @param string $entityClass The entity class name
     * @param array $options Additional options passed from the attribute
     * @return mixed The generated ID value
     */
    public function generate(string $entityClass, array $options = []): mixed;

    /**
     * Get the strategy name/type.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if this strategy supports the given generator type.
     *
     * @param string $generatorType
     * @return bool
     */
    public function supports(string $generatorType): bool;
}
