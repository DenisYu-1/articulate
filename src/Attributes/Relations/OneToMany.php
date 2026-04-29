<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany implements RelationAttributeInterface {
    public readonly ?string $targetEntity;

    public readonly ?string $ownedBy;

    public readonly bool $lazy;

    public function __construct(
        ?string $targetEntity = null,
        ?string $ownedBy = null,
        bool $lazy = false,
    ) {
        $this->targetEntity = $targetEntity;
        $this->ownedBy = $ownedBy;
        $this->lazy = $lazy;
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return null;
    }
}
