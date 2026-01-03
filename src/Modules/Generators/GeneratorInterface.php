<?php

namespace Articulate\Modules\Generators;

/**
 * Interface for ID generators.
 */
interface GeneratorInterface {
    /**
     * Generate a new ID value.
     *
     * @param string $entityClass The entity class name
     * @param array $options Additional options for generation
     * @return mixed The generated ID value
     */
    public function generate(string $entityClass, array $options = []): mixed;

    /**
     * Get the generator type/name.
     *
     * @return string
     */
    public function getType(): string;
}
