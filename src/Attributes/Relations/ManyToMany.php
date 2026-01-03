<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany implements RelationAttributeInterface {
    public readonly ?string $targetEntity;

    public readonly ?string $ownedBy;

    public readonly ?string $referencedBy;

    public readonly ?MappingTable $mappingTable;

    public function __construct(
        ?string $targetEntity = null,
        ?string $ownedBy = null,
        ?string $referencedBy = null,
        ?MappingTable $mappingTable = null,
    ) {
        $this->targetEntity = $targetEntity;
        $this->ownedBy = $ownedBy;
        $this->referencedBy = $referencedBy;
        $this->mappingTable = $mappingTable;
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
