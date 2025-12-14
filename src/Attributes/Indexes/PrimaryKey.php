<?php

namespace Articulate\Attributes\Indexes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey
{
    public function __construct(
    ) {
    }
}
