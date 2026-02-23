<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Collection\MappingCollection;
use Articulate\Schema\SchemaNaming;
use Exception;
use ReflectionNamedType;
use RuntimeException;

class ReflectionManyToMany implements RelationInterface {
    public function __construct(
        private readonly ManyToMany $attribute,
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
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $reflectionEntity = new ReflectionEntity($type->getName());
            if (!$reflectionEntity->isEntity()) {
                throw new RuntimeException('Non-entity found in relation');
            }

            return $type->getName();
        }

        throw new Exception('Target entity is misconfigured');
    }

    public function getDeclaringClassName(): string
    {
        return $this->property->class;
    }

    public function isOwningSide(): bool
    {
        return $this->getMappedBy() === null;
    }

    public function getMappedBy(): ?string
    {
        return $this->attribute->ownedBy;
    }

    public function getInversedBy(): ?string
    {
        return $this->attribute->referencedBy;
    }

    public function getTableName(): string
    {
        if ($this->attribute->mappingTable?->name) {
            return $this->attribute->mappingTable->name;
        }
        $ownerEntity = new ReflectionEntity($this->getDeclaringClassName());
        $targetEntity = new ReflectionEntity($this->getTargetEntity());

        return $this->schemaNaming->mappingTableName($ownerEntity->getTableName(), $targetEntity->getTableName());
    }

    public function getOwnerJoinColumn(): string
    {
        $ownerEntity = new ReflectionEntity($this->getDeclaringClassName());

        return $this->schemaNaming->relationColumn($ownerEntity->getTableName());
    }

    public function getTargetJoinColumn(): string
    {
        $targetEntity = new ReflectionEntity($this->getTargetEntity());

        return $this->schemaNaming->relationColumn($targetEntity->getTableName());
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
        return [$this->getOwnerJoinColumn(), $this->getTargetJoinColumn()];
    }

    public function getPropertyName(): string
    {
        return $this->property->getName();
    }

    public function getAttribute(): ManyToMany
    {
        return $this->attribute;
    }

    private function assertCollectionType(): void
    {
        $type = $this->property->getType();
        if ($type === null) {
            return;
        }
        if (!$type instanceof ReflectionNamedType) {
            return;
        }
        if ($type->isBuiltin()) {
            $allowed = ['array', 'iterable'];
            if (!in_array($type->getName(), $allowed, true)) {
                throw new RuntimeException('Many-to-many property must be iterable collection');
            }

            return;
        }
        $name = $type->getName();
        if ($name !== MappingCollection::class && !is_subclass_of($name, MappingCollection::class)) {
            throw new RuntimeException('Many-to-many property must be array, iterable, or MappingCollection');
        }
    }
}
