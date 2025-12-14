<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany implements RelationAttributeInterface
{
    public readonly ?string $targetEntity;

    public readonly ?string $ownedBy;

    public function __construct(
        ?string $targetEntity = null,
        ?string $ownedBy = null,
    ) {
        $this->targetEntity = $targetEntity;
        $this->ownedBy = $ownedBy;
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
