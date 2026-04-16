<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionProperty as ArticulateReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Schema\HydratorInterface;
use Articulate\Utils\TypeRegistry;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class ObjectHydrator implements HydratorInterface {
    private ?RelationshipLoader $relationshipLoader;

    private LifecycleCallbackManager $callbackManager;

    private TypeRegistry $typeRegistry;

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        ?RelationshipLoader $relationshipLoader = null,
        ?LifecycleCallbackManager $callbackManager = null,
        ?TypeRegistry $typeRegistry = null
    ) {
        $this->relationshipLoader = $relationshipLoader;
        $this->callbackManager = $callbackManager ?? new LifecycleCallbackManager();
        $this->typeRegistry = $typeRegistry ?? new TypeRegistry();
    }

    public function hydrate(string $class, array $data, ?object $entity = null): mixed
    {
        // 1. Create entity instance (or use provided)
        $entity ??= $this->createEntity($class);

        // 2. Convert database types to PHP types and set scalar properties
        $this->hydrateProperties($entity, $data);

        // 3. Handle relations (lazy proxies or eager loading)
        $this->hydrateRelations($entity, $data);

        // 4. Register in identity map
        $this->registerEntity($entity, $data);

        // 5. Call postLoad callbacks
        $this->invokePostLoadCallbacks($entity);

        return $entity;
    }

    public function extract(mixed $entity): array
    {
        $data = [];
        $reflection = new ReflectionClass($entity);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $entity->$name ?? null;

            // Convert PHP types to database types
            $dbValue = $this->convertToDatabase($value, $name, $entity);
            $data[$name] = $dbValue;
        }

        return $data;
    }

    /**
     * Convert database value to PHP type based on property type.
     */
    private function convertToPHP(mixed $dbValue, string $propertyName, object $entity): mixed
    {
        if ($dbValue === null) {
            return null;
        }

        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($propertyName);
        $type = $property->getType();

        if (!$type) {
            // No type hint, return as-is
            return $dbValue;
        }

        $phpType = $this->getPHPTypeFromReflection($type);

        // Get converter for this type
        $converter = $this->typeRegistry->getConverter($phpType);
        if ($converter) {
            return $converter->convertToPHP($dbValue);
        }

        // No converter available, return basic type conversion
        return $this->basicTypeConversion($dbValue, $phpType);
    }

    /**
     * Convert PHP value to database representation.
     */
    private function convertToDatabase(mixed $phpValue, string $propertyName, object $entity): mixed
    {
        if ($phpValue === null) {
            return null;
        }

        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($propertyName);
        $type = $property->getType();

        if (!$type) {
            // No type hint, return as-is
            return $phpValue;
        }

        $phpType = $this->getPHPTypeFromReflection($type);

        // Get converter for this type
        $converter = $this->typeRegistry->getConverter($phpType);
        if ($converter) {
            return $converter->convertToDatabase($phpValue);
        }

        // No converter available, return basic type conversion
        return $this->basicTypeConversionToDatabase($phpValue, $phpType);
    }

    /**
     * Extract PHP type string from ReflectionType.
     */
    private function getPHPTypeFromReflection(\ReflectionType $type): string
    {
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

        if ($type->allowsNull() && !str_starts_with($typeName, '?')) {
            $typeName = '?' . $typeName;
        }

        return $typeName;
    }

    /**
     * Basic type conversion for common types when no converter is available.
     */
    private function basicTypeConversion(mixed $value, string $targetType): mixed
    {
        return match ($targetType) {
            'int', '?int' => is_numeric($value) ? (int) $value : $value,
            'float', '?float' => is_numeric($value) ? (float) $value : $value,
            'string', '?string' => (string) $value,
            'bool', '?bool' => (bool) $value,
            default => $value
        };
    }

    /**
     * Basic type conversion to database (mostly pass-through for common types).
     */
    private function basicTypeConversionToDatabase(mixed $value, string $targetType): mixed
    {
        // For basic types, PHP values are usually already in acceptable database format
        return $value;
    }

    public function hydratePartial(object $entity, array $data): void
    {
        $this->hydrateProperties($entity, $data);
        $this->hydrateRelations($entity, $data);
    }

    private function createEntity(string $class): object
    {
        $reflection = new ReflectionClass($class);

        // Create instance without calling constructor
        return $reflection->newInstanceWithoutConstructor();
    }

    private function hydrateProperties(object $entity, array $data): void
    {
        $reflection = new ReflectionClass($entity);

        foreach ($data as $columnName => $value) {
            // Try to map column to property
            $propertyName = $this->mapColumnToProperty($reflection, $columnName);

            if ($propertyName && $reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);

                // Skip class-typed properties (entities/relations) — handled by hydrateRelations()
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    continue;
                }

                $property->setAccessible(true);

                // Convert database value to PHP type
                $phpValue = $this->convertToPHP($value, $propertyName, $entity);
                $property->setValue($entity, $phpValue);
            }
        }
    }

    private function hydrateRelations(object $entity, array $data): void
    {
        if (!$this->relationshipLoader) {
            return; // No relationship loader configured
        }

        $metadata = $this->relationshipLoader->getMetadataRegistry()->getMetadata($entity::class);
        $em       = null;

        foreach ($metadata->getRelations() as $relationName => $relation) {
            $prop = new ReflectionProperty($entity, $relationName);
            $prop->setAccessible(true);

            if ($prop->isInitialized($entity) && $prop->getValue($entity) !== null) {
                continue;
            }

            // ── Determine whether this relation is a collection type ───────────
            $isManyToMany = $relation instanceof ReflectionManyToMany;
            $isCollectionRelation = $isManyToMany
                || ($relation instanceof ReflectionRelation
                    && ($relation->isOneToMany() || $relation->isManyToMany() || $relation->isMorphMany()));

            if (!$relation->isLazy()) {
                // Eager: load relation immediately.
                $relatedData = $this->relationshipLoader->load($entity, $relation, $data);
                // Wrap in Collection only for relations that use collection-typed properties,
                // not MorphMany (array-typed) or other non-Collection relations.
                if (is_array($relatedData)
                    && ($isManyToMany || ($relation instanceof ReflectionRelation && $relation->isOneToMany()))
                ) {
                    $relatedData = new Collection($relatedData);
                }
                $prop->setValue($entity, $relatedData);
                continue;
            }

            // ── Lazy relations ────────────────────────────────────────────────
            $em ??= $this->relationshipLoader->getEntityManager();

            if ($isCollectionRelation) {
                // Collection — wrap in a LazyCollection with optional COUNT optimisation.
                $loader      = fn () => $this->relationshipLoader->load($entity, $relation);
                $countLoader = ($isManyToMany
                    || ($relation instanceof ReflectionRelation && ($relation->isOneToMany() || $relation->isManyToMany())))
                    ? fn () => $this->relationshipLoader->count($entity, $relation)
                    : null;

                $prop->setValue($entity, new LazyCollection($loader, $countLoader));
            } elseif ($relation instanceof ReflectionRelation && $relation->isOwningSide()) {
                // Owning side with FK in row (ManyToOne, owning OneToOne, MorphTo).
                $fkValue = $data[$relation->getColumnName()] ?? null;
                if ($fkValue !== null) {
                    $prop->setValue($entity, $em->getReference($relation->getTargetEntity(), $fkValue));
                }
            } else {
                // Inverse single entity (inverse OneToOne, MorphOne) — proxy with custom loader.
                $proxy = $em->createLazyReference(
                    $relation->getTargetEntity(),
                    function (ProxyInterface $p) use ($entity, $relation): void {
                        $loaded = $this->relationshipLoader->load($entity, $relation);
                        if ($loaded !== null) {
                            $ref = new ReflectionClass($loaded);
                            foreach ($ref->getProperties() as $rp) {
                                $rp->setAccessible(true);
                                try {
                                    $rp->setValue($p, $rp->getValue($loaded));
                                } catch (\Error) {
                                    // skip read-only or uninitialised properties
                                }
                            }
                            $p->markProxyInitialized();
                        }
                    }
                );
                $prop->setValue($entity, $proxy);
            }
        }
    }

    private function registerEntity(object $entity, array $data): void
    {
        // Extract ID and register in UnitOfWork
        $id = $this->extractEntityId($entity, $data);
        $this->unitOfWork->registerManaged($entity, $data);
    }

    private function invokePostLoadCallbacks(object $entity): void
    {
        $this->callbackManager->invokeCallbacks($entity, 'postLoad');
    }

    private function mapColumnToProperty(ReflectionClass $reflection, string $columnName): ?string
    {
        // Simple mapping: snake_case to camelCase
        $propertyName = $this->snakeToCamel($columnName);

        // Check if property exists
        if ($reflection->hasProperty($propertyName)) {
            return $propertyName;
        }

        // Check for Property attribute mapping
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Property::class);
            foreach ($attributes as $attribute) {
                $propertyAttr = $attribute->newInstance();
                if ($propertyAttr->name === $columnName) {
                    return $property->getName();
                }
            }
        }

        return null;
    }

    private function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }

    private function extractEntityId(object $entity, array $data): mixed
    {
        $primaryKeyProperty = $this->findPrimaryKeyProperty($entity);
        if ($primaryKeyProperty !== null) {
            return $primaryKeyProperty->getValue($entity);
        }

        // Fallback: try to get from data array using primary key column name
        $reflectionEntity = new ReflectionEntity($entity::class);
        $primaryKeyColumns = $reflectionEntity->getPrimaryKeyColumns();
        if (!empty($primaryKeyColumns)) {
            $firstKey = $primaryKeyColumns[0];

            return $data[$firstKey] ?? null;
        }

        return null;
    }

    private function findPrimaryKeyProperty(object $entity): ?ArticulateReflectionProperty
    {
        $reflectionEntity = new ReflectionEntity($entity::class);

        foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
            if ($property instanceof ArticulateReflectionProperty && $property->isPrimaryKey()) {
                return $property;
            }
        }

        return null;
    }
}
