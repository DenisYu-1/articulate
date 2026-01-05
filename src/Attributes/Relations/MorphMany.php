<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphMany implements RelationAttributeInterface {
    use PolymorphicColumnResolution;

    /**
     * @param class-string $targetEntity The entity class this relation morphs to
     * @param string|null $morphType The morph type identifier (defaults to the target entity class name)
     * @param string|null $typeColumn Custom column name for the morph type (default: {property}_type)
     * @param string|null $idColumn Custom column name for the morph ID (default: {property}_id)
     * @param string|null $referencedBy The property on the target entity that references back
     * @param bool $foreignKey Whether to create a foreign key constraint
     */
    public function __construct(
        public readonly string $targetEntity,
        public readonly ?string $morphType = null,
        public readonly ?string $typeColumn = null,
        public readonly ?string $idColumn = null,
        public readonly ?string $referencedBy = null,
        public readonly bool $foreignKey = true,
    ) {
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return $this->idColumn;
    }

    /**
     * Get the morph type identifier.
     */
    public function getMorphType(): string
    {
        return $this->morphType ?? $this->targetEntity;
    }

}
