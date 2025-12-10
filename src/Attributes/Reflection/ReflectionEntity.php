<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Schema\SchemaNaming;
use ReflectionAttribute;
use ReflectionClass;

class ReflectionEntity extends ReflectionClass
{
    public function __construct(
        string $objectOrClass,
        private readonly SchemaNaming $schemaNaming = new SchemaNaming(),
    ) {
        parent::__construct($objectOrClass);
    }

    public function isEntity(): bool
    {
        return !empty($this->getAttributes(Entity::class));
    }

    public function getEntityProperties(): iterable
    {
        if (!$this->isEntity()) {
            yield from [];
            return;
        }
        foreach ($this->getProperties() as $property) {
            /** @var ReflectionAttribute<Property>[] $entityProperty */
            $entityProperty = $property->getAttributes(Property::class);

            if (!empty($entityProperty)) {
                yield new ReflectionProperty(
                    $entityProperty[0]->newInstance(),
                    $property,
                    isset($property->getAttributes(AutoIncrement::class)[0]) ?? false,
                    isset($property->getAttributes(PrimaryKey::class)[0]) ?? false,
                );
                continue;
            }
            /** @var ReflectionAttribute<OneToOne>[] $entityProperty */
            $entityProperty = $property->getAttributes(OneToOne::class);
            if (!empty($entityProperty)) {
                $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
                if (!$relation->isOwningSide()) {
                    continue;
                }
                yield $relation;
                continue;
            }
            /** @var ReflectionAttribute<ManyToOne>[] $entityProperty */
            $entityProperty = $property->getAttributes(ManyToOne::class);
            if (!empty($entityProperty)) {
                $propertyInstance = $entityProperty[0]->newInstance();
                yield new ReflectionRelation($propertyInstance, $property, $this->schemaNaming);
            }
        }
    }

    public function getEntityFieldsProperties(): iterable
    {
        if (!$this->isEntity()) {
            yield from [];
            return;
        }
        foreach ($this->getProperties() as $property) {
            /** @var ReflectionAttribute<OneToOne>[] $entityProperty */
            $entityProperty = $property->getAttributes(OneToOne::class);
            if (empty($entityProperty)) {
                continue;
            }

            $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
            if (!$relation->isOwningSide()) {
                continue;
            }

            yield $relation;
        }
        foreach ($this->getProperties() as $property) {
            /** @var ReflectionAttribute<Property>[] $entityProperty */
            $entityProperty = $property->getAttributes(Property::class);
            if (empty($entityProperty)) {
                continue;
            }
            yield new ReflectionProperty($entityProperty[0]->newInstance(), $property);
        }

    }

    public function getEntityRelationProperties(): iterable
    {
        if (!$this->isEntity()) {
            yield from [];
            return;
        }
        foreach ($this->getProperties() as $property) {
            /** @var ReflectionAttribute<OneToOne>[] $oneToOne */
            $oneToOne = $property->getAttributes(OneToOne::class);
            if (!empty($oneToOne)) {
                yield new ReflectionRelation($oneToOne[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<ManyToOne>[] $manyToOne */
            $manyToOne = $property->getAttributes(ManyToOne::class);
            if (!empty($manyToOne)) {
                yield new ReflectionRelation($manyToOne[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<OneToMany>[] $oneToMany */
            $oneToMany = $property->getAttributes(OneToMany::class);
            if (!empty($oneToMany)) {
                yield new ReflectionRelation($oneToMany[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<ManyToMany>[] $manyToMany */
            $manyToMany = $property->getAttributes(ManyToMany::class);
            if (!empty($manyToMany)) {
                yield new ReflectionManyToMany($manyToMany[0]->newInstance(), $property, $this->schemaNaming);
            }
        }
    }

    public function getPrimaryKeyColumns(): array
    {
        if (!$this->isEntity()) {
            return [];
        }
        $columns = [];
        foreach ($this->getProperties() as $property) {
            $entityProperty = $property->getAttributes(PrimaryKey::class);
            if (empty($entityProperty)) {
                continue;
            }
            $columns[] = $property->getName();
        }
        sort($columns);
        return $columns;
    }

    public function getTableName()
    {
        if (!$this->isEntity()) {
            return null;
        }
        return $this->getAttributes(Entity::class)[0]->newInstance()->tableName ?? $this->parseTableName();
    }

    private function parseTableName(): string
    {
        $className = explode('\\', $this->getName());
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', end($className)));
    }
}
