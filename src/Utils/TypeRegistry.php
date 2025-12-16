<?php

namespace Articulate\Utils;

/**
 * Registry for mapping PHP types to database types and vice versa.
 * Supports custom type converters and bidirectional mapping.
 */
class TypeRegistry
{
    private array $phpToDb = [];
    private array $dbToPhp = [];
    private array $converters = [];
    private array $classMappings = []; // class/interface => db_type

    public function __construct()
    {
        $this->registerBuiltInTypes();
    }

    /**
     * Register a PHP type to database type mapping
     */
    public function registerType(string $phpType, string $dbType, ?TypeConverterInterface $converter = null): void
    {
        $this->phpToDb[$phpType] = $dbType;

        // Only register reverse mapping for non-nullable types to avoid conflicts
        if (!str_starts_with($phpType, '?')) {
            $this->dbToPhp[$dbType] = $phpType;
        }

        if ($converter) {
            $this->converters[$phpType] = $converter;
        }
    }

    /**
     * Register a PHP class or interface to database type mapping
     * All classes implementing/extending this will use the mapping
     */
    public function registerClassMapping(string $classOrInterface, string $dbType, ?TypeConverterInterface $converter = null): void
    {
        $this->classMappings[$classOrInterface] = $dbType;

        // Register converter if provided
        if ($converter) {
            $this->converters[$classOrInterface] = $converter;
        }
    }

    /**
     * Get database type for a PHP type
     */
    public function getDatabaseType(string $phpType): string
    {
        // First check direct type mappings
        if (isset($this->phpToDb[$phpType])) {
            return $this->phpToDb[$phpType];
        }

        // Check if it's a class that implements/extends registered interfaces/classes
        $mappedType = $this->findClassMapping($phpType);
        if ($mappedType !== null) {
            return $mappedType;
        }

        // Fall back to the type itself (for custom database types)
        return $phpType;
    }

    /**
     * Find database type mapping for a class based on implemented interfaces/extended classes
     */
    private function findClassMapping(string $className): ?string
    {
        // Check if class exists
        if (!class_exists($className) && !interface_exists($className)) {
            return null;
        }

        // Check direct class mapping first
        if (isset($this->classMappings[$className])) {
            return $this->classMappings[$className];
        }

        // Check if class implements any registered interfaces
        $interfaces = class_implements($className);
        if ($interfaces) {
            foreach ($interfaces as $interface) {
                if (isset($this->classMappings[$interface])) {
                    return $this->classMappings[$interface];
                }
            }
        }

        // Check if class extends any registered parent classes
        $parents = class_parents($className);
        if ($parents) {
            foreach ($parents as $parent) {
                if (isset($this->classMappings[$parent])) {
                    return $this->classMappings[$parent];
                }
            }
        }

        return null;
    }

    /**
     * Get PHP type for a database type
     */
    public function getPhpType(string $dbType): string
    {
        // Special handling for TINYINT(1) -> bool
        if (preg_match('/TINYINT\(1\)/i', $dbType)) {
            return 'bool';
        }

        // Handle database types with parameters like VARCHAR(255), TINYINT(2)
        $baseType = $this->extractBaseType($dbType);

        return $this->dbToPhp[$baseType] ?? $this->inferPhpType($baseType);
    }

    /**
     * Get type converter for a PHP type
     */
    public function getConverter(string $phpType): ?TypeConverterInterface
    {
        return $this->converters[$phpType] ?? null;
    }

    /**
     * Check if a PHP type has a converter
     */
    public function hasConverter(string $phpType): bool
    {
        return isset($this->converters[$phpType]);
    }

    /**
     * Extract base type from parameterized type like VARCHAR(255)
     */
    private function extractBaseType(string $dbType): string
    {
        // Handle types like VARCHAR(255), TINYINT(1), etc.
        if (preg_match('/^(\w+)/', strtoupper($dbType), $matches)) {
            return $matches[1];
        }

        return $dbType;
    }

    /**
     * Infer PHP type from database type when no explicit mapping exists
     */
    private function inferPhpType(string $dbType): string
    {
        $dbType = strtoupper($dbType);

        return match ($dbType) {
            'INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT' => 'int',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC' => 'float',
            'VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'string',
            'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR' => 'string', // Could be DateTime in future
            'BOOL', 'BOOLEAN' => 'bool',
            default => 'mixed'
        };
    }

    /**
     * Register built-in type mappings
     */
    private function registerBuiltInTypes(): void
    {
        // Basic types (register non-nullable first)
        $this->registerType('int', 'INT');
        $this->registerType('float', 'FLOAT');
        $this->registerType('string', 'VARCHAR(255)');
        $this->registerType('bool', 'TINYINT(1)', new BoolTypeConverter());
        $this->registerType('mixed', 'TEXT');

        // DateTime types (basic mapping for now)
        $this->registerType('DateTime', 'DATETIME');
        $this->registerType('DateTimeImmutable', 'DATETIME');

        // Example custom spatial type (requires spatial extensions)
        $this->registerType(Point::class, 'POINT', new PointTypeConverter());

        // Class/Interface mappings
        $this->registerClassMapping(\DateTimeInterface::class, 'DATETIME');

        // Nullable versions (these override the db->php mapping for reverse lookups)
        $this->registerType('?int', 'INT');
        $this->registerType('?float', 'FLOAT');
        $this->registerType('?string', 'VARCHAR(255)');
        $this->registerType('?bool', 'TINYINT(1)', new BoolTypeConverter());
        $this->registerType('?DateTime', 'DATETIME');
        $this->registerType('?DateTimeImmutable', 'DATETIME');
        $this->registerType('?' . Point::class, 'POINT', new PointTypeConverter());
    }
}
