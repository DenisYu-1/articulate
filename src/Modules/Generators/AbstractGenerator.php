<?php

namespace Articulate\Modules\Generators;

/**
 * Abstract base class for generators.
 */
abstract class AbstractGenerator implements GeneratorInterface {
    /**
     * @var string
     */
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
