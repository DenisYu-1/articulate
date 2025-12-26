<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Collection\MappingCollection;
use Articulate\Schema\SchemaNaming;
use RuntimeException;

class ReflectionMorphedByMany implements RelationInterface
{
    public function __construct(
        private readonly MorphedByMany $attribute,
        private readonly \ReflectionProperty $property,
        private readonly SchemaNaming $schemaNaming = new SchemaNaming(),
    ) {
    }

    public function getTargetEntity(): ?string
    {
        if ($this->attribute->getTargetEntity()) {
            $reflectionEntity = new ReflectionEntity($this->attribute->getTargetEntity());
            if (!$reflectionEntity->isEntity()) {
                throw new RuntimeException('Non-entity found in relation');
            }
            $this->assertCollectionType();

            return $this->attribute->getTargetEntity();
        }
        $this->assertCollectionType();
        $type = $this->property->getType();
        if ($type && !$type->isBuiltin()) {
            $reflectionEntity = new ReflectionEntity($type->getName());
            if (!$reflectionEntity->isEntity()) {
                throw new RuntimeException('Non-entity found in relation');
            }

            return $type->getName();
        }

        throw new RuntimeException('Target entity is misconfigured');
    }

    public function getDeclaringClassName(): string
    {
        return $this->property->class;
    }

    public function isOwningSide(): bool
    {
        return false; // MorphedByMany is always the inverse side
    }

    public function getMappedBy(): ?string
    {
        return null; // MorphedByMany doesn't use mappedBy
    }

    public function getInversedBy(): ?string
    {
        return null; // MorphedByMany doesn't use inversedBy
    }

    public function getTableName(): string
    {
        if ($this->attribute->mappingTable?->name) {
            return $this->attribute->mappingTable->name;
        }

        return $this->attribute->getDefaultMappingTableName();
    }

    public function getOwnerJoinColumn(): string
    {
        return $this->attribute->getIdColumn();
    }

    public function getTargetJoinColumn(): string
    {
        $targetEntity = new ReflectionEntity($this->getTargetEntity());

        return $this->schemaNaming->relationColumn($targetEntity->getTableName());
    }

    public function getTypeColumn(): string
    {
        return $this->attribute->getTypeColumn();
    }

    /**
     * @return MappingTableProperty[]
     */
    public function getExtraProperties(): array
    {
        return $this->attribute->mappingTable?->properties ?? [];
    }

    public function getTargetPrimaryColumn(): string
    {
        $entity = new ReflectionEntity($this->getTargetEntity());
        $columns = $entity->getPrimaryKeyColumns();

        return $columns[0] ?? 'id';
    }

    public function getOwnerPrimaryColumn(): string
    {
        $entity = new ReflectionEntity($this->getDeclaringClassName());
        $columns = $entity->getPrimaryKeyColumns();

        return $columns[0] ?? 'id';
    }

    /**
     * @return string[]
     */
    public function getPrimaryColumns(): array
    {
        return [$this->getTypeColumn(), $this->getOwnerJoinColumn()];
    }

    public function getPropertyName(): string
    {
        return $this->property->getName();
    }

    public function getAttribute(): MorphedByMany
    {
        return $this->attribute;
    }

    public function getMorphName(): string
    {
        return $this->attribute->getMorphName();
    }

    private function assertCollectionType(): void
    {
        $type = $this->property->getType();
        if ($type === null) {
            return;
        }
        if ($type->isBuiltin()) {
            $allowed = ['array', 'iterable'];
            if (!in_array($type->getName(), $allowed, true)) {
                throw new RuntimeException('Morphed-by-many property must be iterable collection');
            }

            return;
        }
        $name = $type->getName();
        if ($name !== MappingCollection::class && !is_subclass_of($name, MappingCollection::class)) {
            throw new RuntimeException('Morphed-by-many property must be array, iterable, or MappingCollection');
        }
    }
}
