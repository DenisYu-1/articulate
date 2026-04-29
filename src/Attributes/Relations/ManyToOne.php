<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne implements RelationAttributeInterface {
    public readonly ?string $targetEntity;

    public readonly ?string $referencedBy;

    public readonly ?string $column;

    public readonly ?bool $nullable;

    public readonly bool $foreignKey;

    public readonly bool $lazy;

    public function __construct(
        ?string $targetEntity = null,
        ?string $referencedBy = null,
        ?string $column = null,
        ?bool $nullable = null,
        bool $foreignKey = true,
        bool $lazy = false,
    ) {
        $this->targetEntity = $targetEntity;
        $this->referencedBy = $referencedBy;
        $this->column = $column;
        $this->nullable = $nullable;
        $this->foreignKey = $foreignKey;
        $this->lazy = $lazy;
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
