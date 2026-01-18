<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Attributes\Relations\RelationAttributeInterface;
use Articulate\Schema\SchemaNaming;
use Exception;
use ReflectionProperty as BaseReflectionProperty;
use RuntimeException;

class ReflectionRelation implements PropertyInterface, RelationInterface {
    public function __construct(
        private readonly RelationAttributeInterface $entityProperty,
        private readonly BaseReflectionProperty $property,
        private readonly SchemaNaming $schemaNaming = new SchemaNaming(),
    ) {
        // Resolve column names for polymorphic relations
        if (method_exists($this->entityProperty, 'resolveColumnNames')) {
            $this->entityProperty->resolveColumnNames($this->property->getName());
        }
    }

    public function getTargetEntity(): ?string
    {
        if ($this->isPolymorphicRelation()) {
            return $this->resolvePolymorphicTarget();
        }

        return $this->resolveRegularTarget();
    }

    /**
     * Checks if this is a polymorphic relation (MorphTo, MorphOne, or MorphMany).
     */
    private function isPolymorphicRelation(): bool
    {
        return $this->isMorphTo() || $this->isMorphOne() || $this->isMorphMany();
    }

    /**
     * Resolves target entity for polymorphic relations.
     */
    private function resolvePolymorphicTarget(): ?string
    {
        if ($this->isMorphTo()) {
            // MorphTo can target any entity at runtime - no single target entity
            // We return null since the target is determined by the morph_type column at runtime
            return null;
        }

        // For MorphOne and MorphMany, validate and return the explicit target entity
        $this->validateAndReturnEntity($this->entityProperty->getTargetEntity(), $this->isMorphMany());

        return $this->entityProperty->getTargetEntity();
    }

    /**
     * Resolves target entity for regular relations.
     */
    private function resolveRegularTarget(): string
    {
        // Check for explicit target entity
        if ($this->entityProperty->getTargetEntity()) {
            $this->validateAndReturnEntity($this->entityProperty->getTargetEntity(), $this->isOneToMany());
            return $this->entityProperty->getTargetEntity();
        }

        // For OneToMany without explicit target, validate collection type
        if ($this->isOneToMany()) {
            $this->assertOneToManyCollectionType();
        }

        // Try to infer target from property type
        $type = $this->property->getType();
        if ($type && !$type->isBuiltin()) {
            $this->validateAndReturnEntity($type->getName(), false);
            return $type->getName();
        }

        throw new Exception('Target entity is misconfigured');
    }

    /**
     * Validates and returns an entity class name.
     */
    private function validateAndReturnEntity(string $entityClass, bool $checkCollectionType): void
    {
        $reflectionEntity = new ReflectionEntity($entityClass);
        if (!$reflectionEntity->isEntity()) {
            throw new RuntimeException('Non-entity found in relation');
        }

        if ($checkCollectionType) {
            $this->assertOneToManyCollectionType();
        }
    }

    public function getMappedBy(): ?string
    {
        if ($this->isManyToOne()) {
            return $this->getMappedByProperty();
        }
        $this->assertMappingConfigured();

        return $this->getMappedByProperty() ?? $this->parseColumnName($this->property->getName());
    }

    public function getInversedBy(): ?string
    {
        if ($this->isManyToOne()) {
            return $this->getInversedByProperty();
        }
        $this->assertMappingConfigured();

        if ($this->getMappedByProperty() && $this->getInversedByProperty()) {
            throw new Exception('ownedBy and referencedBy cannot be specified at the same time');
        }
        if ($this->getInversedByProperty()) {
            return $this->getInversedByProperty();
        }
        $class = $this->property->class;
        $array = explode('\\', $class);

        return $this->getInversedByProperty() ?? $this->parseColumnName(end($array));
    }

    public function isForeignKeyRequired(): bool
    {
        if ($this->isOneToMany()) {
            return false;
        }

        if ($this->isOneToOne() && $this->getMappedByProperty() !== null) {
            return false;
        }

        return property_exists($this->entityProperty, 'foreignKey')
            ? ($this->entityProperty->foreignKey ?? true)
            : true;
    }

    private function parseColumnName(string $name): string
    {
        return $this->schemaNaming->relationColumn($name);
    }

    public function getColumnName(): string
    {
        return $this->entityProperty->getColumn() ?? $this->parseColumnName($this->property->getName());
    }

    public function isNullable(): bool
    {
        if ($this->entityProperty instanceof ManyToOne && $this->entityProperty->nullable !== null) {
            return $this->entityProperty->nullable;
        }

        return $this->property->getType()?->allowsNull() ?? false;
    }

    public function getType(): string
    {
        return 'int';
    }

    public function getDefaultValue(): ?string
    {
        return null;
    }

    public function getLength(): ?int
    {
        return null;
    }

    public function getReferencedTableName(): string
    {
        $reflectionEntity = new ReflectionEntity($this->getTargetEntity());

        return $reflectionEntity->getTableName();
    }

    public function getReferencedColumnName(): string
    {
        $targetEntity = $this->getTargetEntity();
        if ($targetEntity === null) {
            // For MorphTo relations that don't have a specific target entity
            return 'id';
        }

        $reflectionEntity = new ReflectionEntity($targetEntity);
        $primaryColumns = $reflectionEntity->getPrimaryKeyColumns();

        return $primaryColumns[0] ?? 'id';
    }

    public function getPropertyName(): string
    {
        return $this->property->getName();
    }

    /**
     * Get the morph type column name for polymorphic relations.
     */
    public function getMorphTypeColumnName(): string
    {
        if (!$this->isPolymorphic()) {
            throw new RuntimeException('Not a polymorphic relation');
        }

        return $this->entityProperty->getTypeColumn();
    }

    /**
     * Get the morph ID column name for polymorphic relations.
     */
    public function getMorphIdColumnName(): string
    {
        if (!$this->isPolymorphic()) {
            throw new RuntimeException('Not a polymorphic relation');
        }

        return $this->entityProperty->getIdColumn();
    }

    /**
     * Check if this is any kind of polymorphic relation.
     */
    public function isPolymorphic(): bool
    {
        return $this->isMorphTo() || $this->isMorphOne() || $this->isMorphMany();
    }

    /**
     * Get the morph type identifier for owning polymorphic relations.
     */
    public function getMorphType(): string
    {
        if ($this->isMorphOne() || $this->isMorphMany()) {
            return $this->entityProperty->getMorphType();
        }

        throw new RuntimeException('Not an owning polymorphic relation');
    }

    public function getDeclaringClassName(): string
    {
        return $this->property->class;
    }

    public function isOneToOne(): bool
    {
        return $this->entityProperty instanceof OneToOne;
    }

    public function isManyToOne(): bool
    {
        return $this->entityProperty instanceof ManyToOne;
    }

    public function isOneToMany(): bool
    {
        return $this->entityProperty instanceof OneToMany;
    }

    public function isManyToMany(): bool
    {
        return $this->entityProperty instanceof ManyToMany;
    }

    public function isMorphTo(): bool
    {
        return $this->entityProperty instanceof MorphTo;
    }

    public function isMorphOne(): bool
    {
        return $this->entityProperty instanceof MorphOne;
    }

    public function isMorphMany(): bool
    {
        return $this->entityProperty instanceof MorphMany;
    }

    public function isOwningSide(): bool
    {
        if ($this->isManyToOne()) {
            return true;
        }

        if ($this->isOneToOne()) {
            return $this->getMappedByProperty() === null;
        }

        if ($this->isMorphTo()) {
            return true; // MorphTo is always the owning side (contains the columns)
        }

        if ($this->isMorphOne() || $this->isMorphMany()) {
            return false; // MorphOne/MorphMany are inverse sides
        }

        return false;
    }

    public function getMappedByProperty(): ?string
    {
        return property_exists($this->entityProperty, 'ownedBy') ? $this->entityProperty->ownedBy : null;
    }

    public function getInversedByProperty(): ?string
    {
        return property_exists($this->entityProperty, 'referencedBy') ? $this->entityProperty->referencedBy : null;
    }

    private function assertMappingConfigured(): void
    {
        if (empty($this->getMappedByProperty()) && empty($this->getInversedByProperty())) {
            throw new Exception('Either ownedBy or referencedBy is required');
        }
    }

    private function assertOneToManyCollectionType(): void
    {
        $type = $this->property->getType();
        if ($type === null) {
            return;
        }
        if ($type->isBuiltin()) {
            $allowed = ['array', 'iterable'];
            if (!in_array($type->getName(), $allowed, true)) {
                throw new RuntimeException('One-to-many property must be iterable collection');
            }
        }
        // Non-builtin types are accepted here (could be custom collection classes).
    }
}
