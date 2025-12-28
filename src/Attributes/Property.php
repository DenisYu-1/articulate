<?php

namespace Articulate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Property {
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?bool $nullable = null,
        public ?string $defaultValue = null,
        public ?int $maxLength = null,
    ) {
    }
}
