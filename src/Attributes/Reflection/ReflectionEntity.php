<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\MorphToMany;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Schema\SchemaNaming;
use ReflectionAttribute;
use ReflectionClass;

class ReflectionEntity extends ReflectionClass {
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
            /** @var ReflectionAttribute<PrimaryKey>[] $primaryKeyProperty */
            $primaryKeyProperty = $property->getAttributes(PrimaryKey::class);

            // A property is considered an entity property if it has either Property or PrimaryKey attribute
            if (!empty($entityProperty) || !empty($primaryKeyProperty)) {
                // Extract generator type from PrimaryKey attribute
                $generatorType = null;
                if (!empty($primaryKeyProperty)) {
                    $primaryKeyInstance = $primaryKeyProperty[0]->newInstance();
                    $generatorType = $primaryKeyInstance->generator;
                }

                // Use the Property attribute if present, otherwise create a default one
                $propertyAttribute = !empty($entityProperty)
                    ? $entityProperty[0]->newInstance()
                    : new Property(); // Default property configuration for primary keys

                yield new ReflectionProperty(
                    $propertyAttribute,
                    $property,
                    isset($property->getAttributes(AutoIncrement::class)[0]) ?? false,
                    isset($property->getAttributes(PrimaryKey::class)[0]) ?? false,
                    $generatorType,
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

                continue;
            }

            /** @var ReflectionAttribute<MorphTo>[] $entityProperty */
            $entityProperty = $property->getAttributes(MorphTo::class);
            if (!empty($entityProperty)) {
                $propertyInstance = $entityProperty[0]->newInstance();
                yield new ReflectionRelation($propertyInstance, $property, $this->schemaNaming);

                continue;
            }

            /** @var ReflectionAttribute<MorphOne>[] $entityProperty */
            $entityProperty = $property->getAttributes(MorphOne::class);
            if (!empty($entityProperty)) {
                $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
                if (!$relation->isOwningSide()) {
                    continue;
                }
                yield $relation;

                continue;
            }

            /** @var ReflectionAttribute<MorphMany>[] $entityProperty */
            $entityProperty = $property->getAttributes(MorphMany::class);
            if (!empty($entityProperty)) {
                $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
                if (!$relation->isOwningSide()) {
                    continue;
                }
                yield $relation;

                continue;
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

            /** @var ReflectionAttribute<MorphToMany>[] $morphToMany */
            $morphToMany = $property->getAttributes(MorphToMany::class);
            if (!empty($morphToMany)) {
                yield new ReflectionMorphToMany($morphToMany[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<MorphedByMany>[] $morphedByMany */
            $morphedByMany = $property->getAttributes(MorphedByMany::class);
            if (!empty($morphedByMany)) {
                yield new ReflectionMorphedByMany($morphedByMany[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<MorphOne>[] $morphOne */
            $morphOne = $property->getAttributes(MorphOne::class);
            if (!empty($morphOne)) {
                yield new ReflectionRelation($morphOne[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<MorphMany>[] $morphMany */
            $morphMany = $property->getAttributes(MorphMany::class);
            if (!empty($morphMany)) {
                yield new ReflectionRelation($morphMany[0]->newInstance(), $property, $this->schemaNaming);
            }

            /** @var ReflectionAttribute<MorphTo>[] $morphTo */
            $morphTo = $property->getAttributes(MorphTo::class);
            if (!empty($morphTo)) {
                yield new ReflectionRelation($morphTo[0]->newInstance(), $property, $this->schemaNaming);
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
            $primaryKeyAttribute = $property->getAttributes(PrimaryKey::class);
            if (empty($primaryKeyAttribute)) {
                continue;
            }

            // Check if property has Property attribute for custom column name
            $propertyAttribute = $property->getAttributes(Property::class);
            if (!empty($propertyAttribute)) {
                $reflectionProperty = new ReflectionProperty($propertyAttribute[0]->newInstance(), $property);
                $columns[] = $reflectionProperty->getColumnName();
            } else {
                // Fall back to default column name parsing
                $columns[] = strtolower(preg_replace('/\B([A-Z])/', '_$1', $property->getName()));
            }
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
