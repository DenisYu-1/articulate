<?php

namespace Articulate\Attributes\Reflection;

use Exception;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Attributes\Relations\RelationAttributeInterface;
use Articulate\Schema\SchemaNaming;
use ReflectionProperty as BaseReflectionProperty;
use RuntimeException;

class ReflectionRelation implements PropertyInterface
{
    public function __construct(
        private readonly RelationAttributeInterface $entityProperty,
        private readonly BaseReflectionProperty $property,
        private readonly SchemaNaming $schemaNaming = new SchemaNaming(),
    ) {
    }

    public function getTargetEntity(): string
    {
        if ($this->entityProperty->getTargetEntity()) {
            $reflectionEntity = new ReflectionEntity($this->entityProperty->getTargetEntity());
            if (!$reflectionEntity->isEntity()) {
                throw new RuntimeException('Non-entity found in relation');
            }
            if ($this->isOneToMany()) {
                $this->assertOneToManyCollectionType();
            }
            return $this->entityProperty->getTargetEntity();
        }
        if ($this->isOneToMany()) {
            $this->assertOneToManyCollectionType();
        }
        $type = $this->property->getType();

        if ($type && !$type->isBuiltin()) {
            $reflectionEntity = new ReflectionEntity($type->getName());
            if (!$reflectionEntity->isEntity()) {
                throw new RuntimeException('Non-entity found in relation');
            }
            return $type->getName();
        }
        throw new Exception('Target entity is misconfigured');
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
            throw new Exception('mappedBy and inversedBy cannot be specified at the same time');
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
        $reflectionEntity = new ReflectionEntity($this->getTargetEntity());
        $primaryColumns = $reflectionEntity->getPrimaryKeyColumns();
        return $primaryColumns[0] ?? 'id';
    }

    public function getPropertyName(): string
    {
        return $this->property->getName();
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

    public function isOwningSide(): bool
    {
        return $this->isManyToOne() || ($this->isOneToOne() && $this->entityProperty->mainSide);
    }

    public function getMappedByProperty(): ?string
    {
        return property_exists($this->entityProperty, 'mappedBy') ? $this->entityProperty->mappedBy : null;
    }

    public function getInversedByProperty(): ?string
    {
        return property_exists($this->entityProperty, 'inversedBy') ? $this->entityProperty->inversedBy : null;
    }

    private function assertMappingConfigured(): void
    {
        if (empty($this->getMappedByProperty()) && empty($this->getInversedByProperty())) {
            throw new Exception('Either mappedBy or inversedBy is required');
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
