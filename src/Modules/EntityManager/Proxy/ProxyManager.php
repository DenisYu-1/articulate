<?php

namespace Articulate\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\EntityManager;

/**
 * Manages proxy creation and lazy loading of relationships.
 */
class ProxyManager {
    public function __construct(
        private EntityManager $entityManager,
        private ProxyGenerator $proxyGenerator
    ) {
    }

    /**
     * Create a proxy for lazy loading of an entity.
     */
    public function createProxy(string $entityClass, mixed $identifier): ProxyInterface
    {
        $initializer = function (ProxyInterface $proxy) {
            $this->initializeProxy($proxy);
        };

        return $this->proxyGenerator->createProxy($entityClass, $identifier, $initializer, $this);
    }

    /**
     * Initialize a proxy by loading its data.
     */
    public function initializeProxy(ProxyInterface $proxy): void
    {
        if ($proxy->isProxyInitialized()) {
            return;
        }

        // Load the actual entity data
        $entityClass = $proxy->getProxyEntityClass();
        $entity = $this->entityManager->find($entityClass, $proxy->getProxyIdentifier());

        if ($entity) {
            // Copy data from real entity to proxy
            $this->copyEntityData($entity, $proxy);
            $proxy->markProxyInitialized();
        }
    }

    /**
     * Load a relationship lazily.
     */
    public function loadRelation(ProxyInterface $proxy, string $relationName): mixed
    {
        return $this->entityManager->loadRelation($proxy, $relationName);
    }

    /**
     * Copy data from real entity to proxy.
     */
    private function copyEntityData(object $source, object $target): void
    {
        $reflection = new \ReflectionClass($source);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic()) {
                $name = $property->getName();
                $value = $property->getValue($source);
                $target->$name = $value;
            }
        }
    }
}
