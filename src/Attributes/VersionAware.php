<?php

namespace Articulate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class VersionAware {
    /**
     * @param string[] $columns Raw column names this class bumps on UPDATE but never checks.
     */
    public function __construct(
        public array $columns,
    ) {
    }
}
