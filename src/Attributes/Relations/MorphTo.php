<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphTo implements RelationAttributeInterface {
    use PolymorphicColumnResolution;

    /**
     * @param string|null $typeColumn Custom column name for the morph type (default: {property}_type)
     * @param string|null $idColumn Custom column name for the morph ID (default: {property}_id)
     */
    public function __construct(
        public readonly ?string $typeColumn = null,
        public readonly ?string $idColumn = null,
    ) {
    }

    public function getTargetEntity(): ?string
    {
        // MorphTo can target any entity at runtime - no single target entity
        return null;
    }

    public function getColumn(): ?string
    {
        return $this->idColumn;
    }

    /**
     * Get recommended index for this polymorphic relation
     * Returns a composite index on (type_column, id_column).
     */
    public function getRecommendedIndexName(): string
    {
        $typeColumn = $this->getTypeColumn();
        $idColumn = $this->getIdColumn();

        return "idx_{$typeColumn}_{$idColumn}";
    }

    /**
     * Get recommended index columns for this polymorphic relation.
     */
    public function getRecommendedIndexColumns(): array
    {
        return [$this->getTypeColumn(), $this->getIdColumn()];
    }
}
