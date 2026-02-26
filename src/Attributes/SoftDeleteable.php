<?php

namespace Articulate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SoftDeleteable {
    public function __construct(
        public ?string $fieldName = 'deletedAt',
        public ?string $columnName = 'deleted_at'
    ) {
    }
}
