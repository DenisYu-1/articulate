<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne implements RelationAttributeInterface
{
    public readonly ?string $targetEntity;
    public readonly ?string $referencedBy;
    public readonly ?string $column;
    public readonly ?bool $nullable;
    public readonly bool $foreignKey;

    public function __construct(
        ?string $targetEntity = null,
        ?string $referencedBy = null,
        ?string $column = null,
        ?bool $nullable = null,
        bool $foreignKey = true,
    ) {
        $this->targetEntity = $targetEntity;
        $this->referencedBy = $referencedBy;
        $this->column = $column;
        $this->nullable = $nullable;
        $this->foreignKey = $foreignKey;
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }
}
