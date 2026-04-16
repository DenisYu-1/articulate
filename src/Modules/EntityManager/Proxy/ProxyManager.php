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
     * Create a proxy with a fully custom initializer closure.
     * Use this when the identifier is not known upfront (e.g., inverse-side single relations).
     * The identifier is set to null; the closure is responsible for copying data into the proxy
     * and calling $proxy->markProxyInitialized().
     */
    public function createProxyWithCustomLoader(string $entityClass, \Closure $initializer): ProxyInterface
    {
        return $this->proxyGenerator->createProxy($entityClass, null, $initializer, $this);
    }

    /**
     * Initialize a proxy by loading its data.
     */
    public function initializeProxy(ProxyInterface $proxy): void
    {
        if ($proxy->isProxyInitialized()) {
            return;
        }

        $entityClass = $proxy->getProxyEntityClass();
        $entity = $this->entityManager->find($entityClass, $proxy->getProxyIdentifier());

        if ($entity !== null) {
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
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            try {
                $value = $property->getValue($source);
                $property->setValue($target, $value);
            } catch (\Error) {
                try {
                    $property->setValue($target, null);
                } catch (\Error) {
                    // keep silent for properties that cannot be copied safely
                }
            }
        }
    }
}
