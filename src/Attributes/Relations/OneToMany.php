<?php

namespace Norm\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany implements RelationAttributeInterface
{
    public function __construct(
        public readonly ?string $targetEntity = null,
        public readonly ?string $mappedBy = null,
    ) {}

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return null;
    }
}
