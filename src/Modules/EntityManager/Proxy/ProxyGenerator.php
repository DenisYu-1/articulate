<?php

namespace Articulate\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\EntityMetadataRegistry;

/**
 * Generates proxy classes for lazy loading entities.
 */
class ProxyGenerator {
    public function __construct(
        private EntityMetadataRegistry $metadataRegistry
    ) {
    }

    /**
     * Disable caching (useful for testing).
     */
    public function disableCaching(): void
    {
        $this->enableCaching = false;
    }

    /** @var array<string, class-string> */
    private array $generatedProxies = [];

    /** @var bool */
    private bool $enableCaching = true;

    /**
     * Generate a proxy class for the given entity class.
     */
    public function generateProxyClass(string $entityClass): string
    {
        // Check if proxy already exists and caching is enabled
        if ($this->enableCaching && isset($this->generatedProxies[$entityClass])) {
            return $this->generatedProxies[$entityClass];
        }

        // Generate unique proxy class name
        $proxyClassName = $this->generateProxyClassName($entityClass);

        // Generate and evaluate proxy class code
        $proxyCode = $this->generateProxyClassCode($entityClass, $proxyClassName);
        eval($proxyCode);

        if ($this->enableCaching) {
            $this->generatedProxies[$entityClass] = $proxyClassName;
        }

        return $proxyClassName;
    }

    /**
     * Create a proxy instance.
     */
    public function createProxy(string $entityClass, mixed $identifier, callable $initializer, object $proxyManager): ProxyInterface
    {
        $proxyClassName = $this->generateProxyClass($entityClass);
        $proxy = new $proxyClassName();
        $proxy->_initializeProxy($entityClass, $identifier, $initializer, $proxyManager);

        return $proxy;
    }

    /**
     * Generate a unique proxy class name.
     */
    private function generateProxyClassName(string $entityClass): string
    {
        $hash = substr(md5($entityClass . microtime(true) . rand()), 0, 8);
        $className = basename(str_replace('\\', '_', $entityClass));

        return sprintf('Proxy_%s_%s', $className, $hash);
    }

    /**
     * Generate the PHP code for a proxy class.
     */
    private function generateProxyClassCode(string $entityClass, string $proxyClassName): string
    {
        // Get metadata to know which properties to exclude from the proxy
        $metadata = $this->metadataRegistry->getMetadata($entityClass);
        $propertiesToExclude = array_keys($metadata->getProperties());

        // Generate property declarations excluding entity properties
        $excludedProps = array_map(fn ($prop) => "'$prop'", $propertiesToExclude);
        $excludeList = implode(', ', $excludedProps);

        return <<<PHP
class $proxyClassName extends \\$entityClass implements \\Articulate\\Modules\\EntityManager\\Proxy\\ProxyInterface {
    use \\Articulate\\Modules\\EntityManager\\Proxy\\ProxyTrait;

    private array \$_excludedProperties = [$excludeList];

    public function __get(string \$name): mixed {
        // If this is an entity property, trigger lazy loading
        if (in_array(\$name, \$this->_excludedProperties)) {
            \$this->initializeProxy();
        }
        return parent::__get(\$name) ?? \$this->\$name ?? null;
    }

    public function __set(string \$name, mixed \$value): void {
        // If this is an entity property, trigger lazy loading
        if (in_array(\$name, \$this->_excludedProperties)) {
            \$this->initializeProxy();
        }
        parent::__set(\$name, \$value);
        \$this->\$name = \$value;
    }

    public function __isset(string \$name): bool {
        // If this is an entity property, trigger lazy loading
        if (in_array(\$name, \$this->_excludedProperties)) {
            \$this->initializeProxy();
        }
        return parent::__isset(\$name) || isset(\$this->\$name);
    }
}
PHP;
    }
}
