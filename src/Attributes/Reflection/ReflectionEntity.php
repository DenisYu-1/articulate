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
            if ($result = $this->processPropertyAttribute($property)) {
                yield $result;

                continue;
            }
            if ($result = $this->processOneToOneAttribute($property)) {
                yield $result;

                continue;
            }
            if ($result = $this->processManyToOneAttribute($property)) {
                yield $result;

                continue;
            }
            if ($result = $this->processMorphToAttribute($property)) {
                yield $result;

                continue;
            }
            if ($result = $this->processMorphOneAttribute($property)) {
                yield $result;

                continue;
            }
            if ($result = $this->processMorphManyAttribute($property)) {
                yield $result;

                continue;
            }
        }
    }

    /**
     * Processes Property and PrimaryKey attributes for a given property.
     * Returns the ReflectionProperty if found, null otherwise.
     */
    private function processPropertyAttribute(\ReflectionProperty $property): ?ReflectionProperty
    {
        /** @var ReflectionAttribute<Property>[] $entityProperty */
        $entityProperty = $property->getAttributes(Property::class, ReflectionAttribute::IS_INSTANCEOF);
        /** @var ReflectionAttribute<PrimaryKey>[] $primaryKeyProperty */
        $primaryKeyProperty = $property->getAttributes(PrimaryKey::class);

        // A property is considered an entity property if it has either Property or PrimaryKey attribute
        if (empty($entityProperty) && empty($primaryKeyProperty)) {
            return null;
        }

        $propertyAttribute = $this->resolvePropertyAttribute($entityProperty, $primaryKeyProperty);
        $isPrimaryKey = $this->determineIsPrimaryKey($primaryKeyProperty, $propertyAttribute);

        [$generatorType, $sequence, $generatorOptions] = $this->extractGeneratorInfo($primaryKeyProperty, $propertyAttribute, $isPrimaryKey);

        return new ReflectionProperty(
            $propertyAttribute,
            $property,
            isset($property->getAttributes(AutoIncrement::class)[0]) ?? false,
            $isPrimaryKey,
            $generatorType,
            $sequence,
            $generatorOptions,
        );
    }

    /**
     * Resolves which property attribute to use based on available attributes.
     */
    private function resolvePropertyAttribute(array $entityProperty, array $primaryKeyProperty): Property
    {
        // Find the explicit Property attribute (not PrimaryKey)
        $explicitProperty = null;
        foreach ($entityProperty as $attr) {
            $instance = $attr->newInstance();
            if (!$instance instanceof PrimaryKey) {
                $explicitProperty = $instance;

                break;
            }
        }

        // Use explicit Property if available, otherwise use PrimaryKey
        if ($explicitProperty !== null) {
            return $explicitProperty;
        }

        if (!empty($primaryKeyProperty)) {
            return $primaryKeyProperty[0]->newInstance();
        }

        // Fallback to first Property attribute
        return $entityProperty[0]->newInstance();
    }

    /**
     * Determines if a property is a primary key.
     */
    private function determineIsPrimaryKey(array $primaryKeyProperty, Property $propertyAttribute): bool
    {
        return !empty($primaryKeyProperty) || $propertyAttribute instanceof PrimaryKey;
    }

    /**
     * Extracts generator information from primary key attributes.
     */
    private function extractGeneratorInfo(array $primaryKeyProperty, Property $propertyAttribute, bool $isPrimaryKey): array
    {
        if (!$isPrimaryKey) {
            return [null, null, null];
        }

        // Get generator info from PrimaryKey attribute
        $primaryKeyInstance = !empty($primaryKeyProperty)
            ? $primaryKeyProperty[0]->newInstance()
            : ($propertyAttribute instanceof PrimaryKey ? $propertyAttribute : null);

        if (!$primaryKeyInstance) {
            return [null, null, null];
        }

        return [
            $primaryKeyInstance->generator,
            $primaryKeyInstance->sequence,
            $primaryKeyInstance->options,
        ];
    }

    /**
     * Processes OneToOne attributes for a given property.
     * Returns the ReflectionRelation if found, null otherwise.
     */
    private function processOneToOneAttribute(\ReflectionProperty $property): ?ReflectionRelation
    {
        /** @var ReflectionAttribute<OneToOne>[] $entityProperty */
        $entityProperty = $property->getAttributes(OneToOne::class);
        if (empty($entityProperty)) {
            return null;
        }

        $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
        if (!$relation->isOwningSide()) {
            return null;
        }

        return $relation;
    }

    /**
     * Processes ManyToOne attributes for a given property.
     * Returns the ReflectionRelation if found, null otherwise.
     */
    private function processManyToOneAttribute(\ReflectionProperty $property): ?ReflectionRelation
    {
        /** @var ReflectionAttribute<ManyToOne>[] $entityProperty */
        $entityProperty = $property->getAttributes(ManyToOne::class);
        if (empty($entityProperty)) {
            return null;
        }

        $propertyInstance = $entityProperty[0]->newInstance();

        return new ReflectionRelation($propertyInstance, $property, $this->schemaNaming);
    }

    /**
     * Processes MorphTo attributes for a given property.
     * Returns the ReflectionRelation if found, null otherwise.
     */
    private function processMorphToAttribute(\ReflectionProperty $property): ?ReflectionRelation
    {
        /** @var ReflectionAttribute<MorphTo>[] $entityProperty */
        $entityProperty = $property->getAttributes(MorphTo::class);
        if (empty($entityProperty)) {
            return null;
        }

        $propertyInstance = $entityProperty[0]->newInstance();

        return new ReflectionRelation($propertyInstance, $property, $this->schemaNaming);
    }

    /**
     * Processes MorphOne attributes for a given property.
     * Returns the ReflectionRelation if found, null otherwise.
     */
    private function processMorphOneAttribute(\ReflectionProperty $property): ?ReflectionRelation
    {
        /** @var ReflectionAttribute<MorphOne>[] $entityProperty */
        $entityProperty = $property->getAttributes(MorphOne::class);
        if (empty($entityProperty)) {
            return null;
        }

        $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
        if (!$relation->isOwningSide()) {
            return null;
        }

        return $relation;
    }

    /**
     * Processes MorphMany attributes for a given property.
     * Returns the ReflectionRelation if found, null otherwise.
     */
    private function processMorphManyAttribute(\ReflectionProperty $property): ?ReflectionRelation
    {
        /** @var ReflectionAttribute<MorphMany>[] $entityProperty */
        $entityProperty = $property->getAttributes(MorphMany::class);
        if (empty($entityProperty)) {
            return null;
        }

        $relation = new ReflectionRelation($entityProperty[0]->newInstance(), $property, $this->schemaNaming);
        if (!$relation->isOwningSide()) {
            return null;
        }

        return $relation;
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
            $entityProperty = $property->getAttributes(Property::class, ReflectionAttribute::IS_INSTANCEOF);
            if (empty($entityProperty)) {
                continue;
            }

            // Find the explicit Property attribute (not PrimaryKey) or use PrimaryKey
            $explicitProperty = null;
            foreach ($entityProperty as $attr) {
                $instance = $attr->newInstance();
                if (!$instance instanceof PrimaryKey) {
                    $explicitProperty = $instance;

                    break;
                }
            }

            // Use explicit Property if available, otherwise use PrimaryKey
            if ($explicitProperty !== null) {
                $propertyAttribute = $explicitProperty;
            } else {
                $propertyAttribute = $entityProperty[0]->newInstance();
            }

            yield new ReflectionProperty($propertyAttribute, $property);
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
            $primaryKeyAttributes = $property->getAttributes(PrimaryKey::class);
            if (!empty($primaryKeyAttributes)) {
                $primaryKeyAttribute = $primaryKeyAttributes[0]->newInstance();
                $reflectionProperty = new ReflectionProperty($primaryKeyAttribute, $property);
                $columns[] = $reflectionProperty->getColumnName();
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

    public function getRepositoryClass(): ?string
    {
        if (!$this->isEntity()) {
            return null;
        }

        return $this->getAttributes(Entity::class)[0]->newInstance()->repositoryClass;
    }

    private function parseTableName(): string
    {
        $className = explode('\\', $this->getName());

        return strtolower(preg_replace('/\B([A-Z])/', '_$1', end($className)));
    }
}
