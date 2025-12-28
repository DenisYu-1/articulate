<?php

namespace Articulate\Modules\EntityManager\Proxy;

/**
 * Trait that provides lazy loading functionality for proxy objects.
 */
trait ProxyTrait
{
    private bool $_initialized = false;

    private string $_entityClass;

    private mixed $_identifier;

    private ?\Closure $_initializer = null;

    private ?object $_proxyManager = null;

    /**
     * Initialize the proxy with entity information.
     */
    public function _initializeProxy(string $entityClass, mixed $identifier, ?\Closure $initializer = null, ?object $proxyManager = null): void
    {
        $this->_entityClass = $entityClass;
        $this->_identifier = $identifier;
        $this->_initializer = $initializer;
        $this->_proxyManager = $proxyManager;
    }

    /**
     * Check if proxy is initialized.
     */
    public function isProxyInitialized(): bool
    {
        return $this->_initialized;
    }

    /**
     * Initialize the proxy by loading data.
     */
    public function initializeProxy(): void
    {
        if (!$this->_initialized && $this->_initializer) {
            ($this->_initializer)($this);
            $this->_initialized = true;
        }
    }

    /**
     * Mark proxy as initialized.
     */
    public function markProxyInitialized(): void
    {
        $this->_initialized = true;
    }

    /**
     * Get the entity class this proxy represents.
     */
    public function getProxyEntityClass(): string
    {
        return $this->_entityClass;
    }

    /**
     * Get the entity identifier.
     */
    public function _getIdentifier(): mixed
    {
        return $this->_identifier;
    }

    /**
     * Magic getter that triggers lazy loading.
     */
    public function __get(string $name): mixed
    {
        $this->initializeProxy();

        return $this->$name ?? null;
    }

    /**
     * Magic setter that triggers lazy loading.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->initializeProxy();
        $this->$name = $value;
    }

    /**
     * Magic isset that triggers lazy loading.
     */
    public function __isset(string $name): bool
    {
        $this->initializeProxy();

        return isset($this->$name);
    }

    /**
     * Magic unset that triggers lazy loading.
     */
    public function __unset(string $name): void
    {
        $this->initializeProxy();
        unset($this->$name);
    }

    /**
     * Magic call that triggers lazy loading.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->initializeProxy();

        return $this->$name(...$arguments);
    }

    /**
     * Load a relationship lazily.
     */
    protected function _loadRelation(string $relationName): mixed
    {
        if ($this->_proxyManager && method_exists($this->_proxyManager, 'loadRelation')) {
            return $this->_proxyManager->loadRelation($this, $relationName);
        }

        return null;
    }
}
