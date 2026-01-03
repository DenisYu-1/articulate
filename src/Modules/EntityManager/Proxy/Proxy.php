<?php

namespace Articulate\Modules\EntityManager\Proxy;

/**
 * Base proxy class that implements lazy loading functionality.
 * Generated proxy classes will extend this class.
 */
abstract class Proxy implements ProxyInterface {
    private bool $initialized = false;

    private string $entityClass;

    private mixed $identifier;

    public function __construct(string $entityClass, mixed $identifier)
    {
        $this->entityClass = $entityClass;
        $this->identifier = $identifier;
    }

    public function isProxyInitialized(): bool
    {
        return $this->initialized;
    }

    public function markProxyInitialized(): void
    {
        $this->initialized = true;
    }

    public function getProxyEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getProxyIdentifier(): mixed
    {
        return $this->identifier;
    }

    /**
     * Magic method to intercept property access and trigger lazy loading.
     */
    public function __get(string $name): mixed
    {
        $this->initializeProxy();

        return $this->$name;
    }

    /**
     * Magic method to intercept property setting.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->initializeProxy();
        $this->$name = $value;
    }

    /**
     * Magic method to intercept isset checks.
     */
    public function __isset(string $name): bool
    {
        $this->initializeProxy();

        return isset($this->$name);
    }

    /**
     * Magic method to intercept unset calls.
     */
    public function __unset(string $name): void
    {
        $this->initializeProxy();
        unset($this->$name);
    }

    /**
     * Magic method to intercept method calls and trigger lazy loading.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->initializeProxy();

        return $this->$name(...$arguments);
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        if (!$this->initialized) {
            return sprintf('Proxy<%s#%s>', $this->entityClass, $this->identifier);
        }

        return (string) $this;
    }
}
