<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne implements RelationAttributeInterface
{
    public readonly ?string $targetEntity;
    public readonly ?string $ownedBy;
    public readonly ?string $referencedBy;
    public readonly ?string $column;
    public readonly bool $foreignKey;

    public function __construct(
        ?string $targetEntity = null,
        ?string $ownedBy = null,
        ?string $referencedBy = null,
        ?string $column = null,
        bool $foreignKey = true,
    ) {
        $this->targetEntity = $targetEntity;
        $this->ownedBy = $ownedBy;
        $this->referencedBy = $referencedBy;
        $this->column = $column;
        $this->foreignKey = ($this->ownedBy !== null) ? false : $foreignKey;
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return $this->column ?? $this->ownedBy;
    }
}
