<?php

namespace Articulate\Modules\Database;

use Articulate\Utils\BoolTypeConverter;
use Articulate\Utils\Point;
use Articulate\Utils\PointTypeConverter;
use Articulate\Utils\TypeRegistry;

/**
 * MySQL-specific type mapper with MySQL-specific type mappings.
 */
class MySqlTypeMapper extends TypeRegistry {
    protected function registerBuiltInTypes(): void
    {
        // Basic types (register non-nullable first) - using MySQL-specific syntax
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
        $this->registerClassMapping(\DateTimeInterface::class, 'DATETIME', null, 10); // High priority for DateTime

        // Nullable versions (these override the db->php mapping for reverse lookups)
        $this->registerType('?int', 'INT');
        $this->registerType('?float', 'FLOAT');
        $this->registerType('?string', 'VARCHAR(255)');
        $this->registerType('?bool', 'TINYINT(1)', new BoolTypeConverter());
        $this->registerType('?DateTime', 'DATETIME');
        $this->registerType('?DateTimeImmutable', 'DATETIME');
        $this->registerType('?' . Point::class, 'POINT', new PointTypeConverter());
    }

    public function getPhpType(string $dbType): string
    {
        // MySQL-specific: Special handling for TINYINT(1) -> bool
        if (preg_match('/TINYINT\(1\)/i', $dbType)) {
            return 'bool';
        }

        // Handle database types with parameters like VARCHAR(255), TINYINT(2)
        $baseType = $this->extractBaseType($dbType);

        return $this->dbToPhp[$baseType] ?? $this->inferPhpType($baseType);
    }
}
