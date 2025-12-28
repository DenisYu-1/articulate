<?php

namespace Articulate\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Hydrator that creates lazy-loading proxies instead of real entities.
 */
class LazyLoadingHydrator implements HydratorInterface
{
    public function __construct(
        private UnitOfWork $unitOfWork,
        private ProxyManager $proxyManager,
        private EntityMetadataRegistry $metadataRegistry
    ) {
    }

    public function hydrate(string $class, array $data, ?object $entity = null): mixed
    {
        // For lazy loading, we create a proxy that will load data on first access
        if (!$entity) {
            // Extract identifier from data
            $identifier = $this->extractIdentifier($class, $data);

            if ($identifier !== null) {
                // Create proxy instead of real entity
                $entity = $this->proxyManager->createProxy($class, $identifier);
            } else {
                // Fallback to real entity if no identifier
                $entity = $this->createRealEntity($class, $data);
            }
        }

        return $entity;
    }

    public function extract(mixed $entity): array
    {
        if ($entity instanceof ProxyInterface && !$entity->isProxyInitialized()) {
            // Proxy not initialized, can't extract data
            throw new RuntimeException('Cannot extract data from uninitialized proxy');
        }

        // Use reflection to extract data
        $data = [];
        $reflection = new ReflectionClass($entity);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $data[$name] = $property->getValue($entity);
        }

        return $data;
    }

    public function hydratePartial(object $entity, array $data): void
    {
        // For lazy loading, we don't do partial hydration
        // The proxy will handle full loading when accessed
    }

    /**
     * Extract identifier from data for proxy creation.
     */
    private function extractIdentifier(string $class, array $data): mixed
    {
        $metadata = $this->metadataRegistry->getMetadata($class);
        $primaryKeys = $metadata->getPrimaryKeyColumns();

        if (empty($primaryKeys)) {
            return null;
        }

        // For single primary key
        $primaryKey = $primaryKeys[0];
        $propertyName = $metadata->getPropertyNameForColumn($primaryKey);

        return $data[$propertyName] ?? $data[$primaryKey] ?? null;
    }

    /**
     * Create a real entity as fallback.
     */
    private function createRealEntity(string $class, array $data): object
    {
        $entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        // Convert snake_case to camelCase and set properties
        foreach ($data as $key => $value) {
            $propertyName = $this->snakeToCamel($key);
            if (property_exists($entity, $propertyName)) {
                $entity->$propertyName = $value;
            }
        }

        // Register in UnitOfWork
        $this->unitOfWork->registerManaged($entity, $data);

        return $entity;
    }

    /**
     * Convert snake_case to camelCase.
     */
    private function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }
}
