<?php

namespace Articulate\Modules\EntityManager\Proxy;

use Articulate\Schema\EntityMetadataRegistry;

/**
 * Generates proxy classes for lazy loading entities.
 */
class ProxyGenerator {
    /** @var array<string, class-string> */
    private array $generatedProxies = [];

    /** @var bool */
    private bool $enableCaching = true;

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

        if (!class_exists($proxyClassName, false)) {
            $proxyCode = $this->generateProxyClassCode($entityClass, $proxyClassName);
            eval($proxyCode);
        }

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
        $hash = substr(sha1($entityClass), 0, 12);
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
        $relationProperties = array_keys($metadata->getRelations());

        // Generate property declarations excluding entity properties
        $excludedProps = array_map(fn ($prop) => "'$prop'", $propertiesToExclude);
        $excludeList = implode(', ', $excludedProps);
        $relationProps = array_map(fn ($prop) => "'$prop'", $relationProperties);
        $relationList = implode(', ', $relationProps);

        $hasParentGet = method_exists($entityClass, '__get');
        $hasParentSet = method_exists($entityClass, '__set');
        $hasParentIsset = method_exists($entityClass, '__isset');

        $getBody = $hasParentGet
            ? 'return parent::__get($name);'
            : 'return $this->$name ?? null;';

        $setBody = $hasParentSet
            ? 'parent::__set($name, $value);'
            : '$this->$name = $value;';

        $issetBody = $hasParentIsset
            ? 'return parent::__isset($name);'
            : 'return isset($this->$name);';

        return <<<PHP
class $proxyClassName extends \\$entityClass implements \\Articulate\\Modules\\EntityManager\\Proxy\\ProxyInterface {
    use \\Articulate\\Modules\\EntityManager\\Proxy\\ProxyTrait;

    private array \$_excludedProperties = [$excludeList];
    private array \$_relationProperties = [$relationList];

    public function _initializeProxy(string \$entityClass, mixed \$identifier, ?\Closure \$initializer = null, ?object \$proxyManager = null): void {
        \$this->_entityClass = \$entityClass;
        \$this->_identifier = \$identifier;
        \$this->_initializer = \$initializer;
        \$this->_proxyManager = \$proxyManager;
        foreach (\$this->_relationProperties as \$prop) {
            unset(\$this->\$prop);
        }
        foreach (\$this->_excludedProperties as \$prop) {
            unset(\$this->\$prop);
        }
    }

    public function __get(string \$name): mixed {
        if (in_array(\$name, \$this->_relationProperties, true)) {
            if (!array_key_exists(\$name, \$this->_dynamicProperties)) {
                \$this->_dynamicProperties[\$name] = \$this->_loadRelation(\$name);
            }

            return \$this->_dynamicProperties[\$name];
        }

        if (in_array(\$name, \$this->_excludedProperties)) {
            \$this->initializeProxy();
        }
        $getBody
    }

    public function __set(string \$name, mixed \$value): void {
        if (in_array(\$name, \$this->_relationProperties, true)) {
            \$this->_dynamicProperties[\$name] = \$value;

            return;
        }

        $setBody
    }

    public function __isset(string \$name): bool {
        if (in_array(\$name, \$this->_relationProperties, true)) {
            if (!array_key_exists(\$name, \$this->_dynamicProperties)) {
                \$this->_dynamicProperties[\$name] = \$this->_loadRelation(\$name);
            }

            return isset(\$this->_dynamicProperties[\$name]);
        }

        if (in_array(\$name, \$this->_excludedProperties)) {
            \$this->initializeProxy();
        }
        $issetBody
    }
}
PHP;
    }
}
