<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Property;
use ReflectionClass;
use ReflectionProperty;

class ObjectHydrator implements HydratorInterface
{
    private ?RelationshipLoader $relationshipLoader;

    private LifecycleCallbackManager $callbackManager;

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        ?RelationshipLoader $relationshipLoader = null,
        ?LifecycleCallbackManager $callbackManager = null
    ) {
        $this->relationshipLoader = $relationshipLoader;
        $this->callbackManager = $callbackManager ?? new LifecycleCallbackManager();
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

            // TODO: Convert PHP types to database types
            $data[$name] = $value;
        }

        return $data;
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
                $property->setAccessible(true);

                // TODO: Type conversion from database to PHP types
                $property->setValue($entity, $value);
            }
        }
    }

    private function hydrateRelations(object $entity, array $data): void
    {
        if (!$this->relationshipLoader) {
            return; // No relationship loader configured
        }

        // Get entity metadata
        $entityClass = $entity::class;
        $metadata = $this->relationshipLoader->getMetadataRegistry()->getMetadata($entityClass);

        // Load relationships
        foreach ($metadata->getRelations() as $relationName => $relation) {
            // For now, only load relationships if they are not already set
            // In a full implementation, you'd have eager/lazy loading configuration
            $reflectionProperty = new ReflectionProperty($entity, $relationName);
            $reflectionProperty->setAccessible(true);

            // Only load if the property is not already initialized or is null
            if (!$reflectionProperty->isInitialized($entity) || $reflectionProperty->getValue($entity) === null) {
                $relatedData = $this->relationshipLoader->load($entity, $relation);

                // Wrap collections in Collection objects for OneToMany/ManyToMany
                if (is_array($relatedData) && ($relation->isOneToMany() || $relation->isManyToMany())) {
                    $relatedData = new Collection($relatedData);
                }

                $reflectionProperty->setValue($entity, $relatedData);
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
        // TODO: Extract ID based on entity metadata (primary key)
        // For now, assume there's an 'id' property
        return $data['id'] ?? null;
    }
}
