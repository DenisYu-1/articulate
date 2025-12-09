<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne implements RelationAttributeInterface
{
    public function __construct(
        public readonly ?string $targetEntity = null,
        public readonly ?string $inversedBy = null,
        public readonly ?string $column = null,
        public readonly ?bool $nullable = null,
        public readonly bool $foreignKey = true,
    ) {}

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }
}
