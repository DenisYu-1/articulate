<?php

namespace Articulate\Modules\EntityManager\Proxy;

/**
 * Interface for proxy objects that support lazy loading.
 */
interface ProxyInterface {
    /**
     * Check if the proxy has been initialized (data loaded).
     */
    public function isProxyInitialized(): bool;

    /**
     * Initialize the proxy by loading the actual data.
     */
    public function initializeProxy(): void;

    /**
     * Mark the proxy as initialized.
     */
    public function markProxyInitialized(): void;

    /**
     * Get the class name of the real entity this proxy represents.
     */
    public function getProxyEntityClass(): string;

    /**
     * Get the entity identifier.
     */
    public function getProxyIdentifier(): mixed;
}
