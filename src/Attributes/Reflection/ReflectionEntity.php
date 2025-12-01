<?php

namespace Norm\Attributes\Reflection;

use Norm\Attributes\Entity;
use Norm\Attributes\Indexes\AutoIncrement;
use Norm\Attributes\Indexes\PrimaryKey;
use Norm\Attributes\Property;
use Norm\Attributes\Relations\ManyToOne;
use Norm\Attributes\Relations\OneToOne;
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
            $entityProperty = $property->getAttributes(OneToOne::class);
            if (!empty($entityProperty)) {
                $propertyInstance = $entityProperty[0]->newInstance();
                if (!$propertyInstance->mainSide) {
                    continue;
                }
                yield new ReflectionRelation($propertyInstance, $property);
                continue;
            }
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
            $entityProperty = $property->getAttributes(OneToOne::class);
            if (empty($entityProperty)) {
                continue;
            }

            if (!$entityProperty->mainSide) {
                continue;
            }

            yield new ReflectionRelation($entityProperty[0]->newInstance(), $property);
        }
        foreach ($this->getProperties() as $property) {
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
