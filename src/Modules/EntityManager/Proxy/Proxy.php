<?php

namespace Articulate\Modules\EntityManager\Proxy;

/**
 * Base proxy class that implements lazy loading functionality.
 * Generated proxy classes will extend this class.
 */
abstract class Proxy implements ProxyInterface {
    use ProxyTrait;

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return $this->isProxyInitialized()
            ? sprintf('Proxy<%s#%s>', $this->getProxyEntityClass(), $this->getProxyIdentifier())
            : sprintf('UninitializedProxy<%s#%s>', $this->getProxyEntityClass(), $this->getProxyIdentifier());
    }
}
