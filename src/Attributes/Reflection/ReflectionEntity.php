<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToOne;
use ReflectionAttribute;
use ReflectionClass;

class ReflectionEntity extends ReflectionClass
{
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
                $propertyInstance = $entityProperty[0]->newInstance();
                if (!$propertyInstance->mainSide) {
                    continue;
                }
                yield new ReflectionRelation($propertyInstance, $property);
                continue;
            }
            /** @var ReflectionAttribute<ManyToOne>[] $entityProperty */
            $entityProperty = $property->getAttributes(ManyToOne::class);
            if (!empty($entityProperty)) {
                $propertyInstance = $entityProperty[0]->newInstance();
                yield new ReflectionRelation($propertyInstance, $property);
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

            $oneToOne = $entityProperty[0]->newInstance();
            if (!$oneToOne->mainSide) {
                continue;
            }

            yield new ReflectionRelation($oneToOne, $property);
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
            /** @var ReflectionAttribute<OneToOne>[] $entityProperty */
            $entityProperty = $property->getAttributes(OneToOne::class);
            if (empty($entityProperty)) {
                continue;
            }

            yield new ReflectionRelation($entityProperty[0]->newInstance(), $property);
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
