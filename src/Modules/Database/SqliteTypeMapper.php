<?php

namespace Articulate\Modules\Database;

use Articulate\Utils\BoolTypeConverter;
use Articulate\Utils\TypeRegistry;

/**
 * SQLite-specific type mapper with SQLite-specific type mappings.
 */
class SqliteTypeMapper extends TypeRegistry {
    protected function registerBuiltInTypes(): void
    {
        // Basic types (register non-nullable first) - using SQLite syntax
        $this->registerType('int', 'INTEGER');
        $this->registerType('float', 'REAL');
        $this->registerType('string', 'TEXT');
        $this->registerType('bool', 'INTEGER', new BoolTypeConverter()); // SQLite uses INTEGER for boolean (0/1)
        $this->registerType('mixed', 'TEXT');

        // DateTime types
        $this->registerType('DateTime', 'TEXT'); // SQLite stores dates as TEXT
        $this->registerType('DateTimeImmutable', 'TEXT');

        // SQLite-specific types
        $this->registerType('uuid', 'TEXT');

        // Class/Interface mappings
        $this->registerClassMapping(\DateTimeInterface::class, 'TEXT', null, 10); // High priority for DateTime

        // Nullable versions (these override the db->php mapping for reverse lookups)
        $this->registerType('?int', 'INTEGER');
        $this->registerType('?float', 'REAL');
        $this->registerType('?string', 'TEXT');
        $this->registerType('?bool', 'INTEGER', new BoolTypeConverter());
        $this->registerType('?DateTime', 'TEXT');
        $this->registerType('?DateTimeImmutable', 'TEXT');
        $this->registerType('?uuid', 'TEXT');
    }

    public function getPhpType(string $dbType): string
    {
        $dbType = strtoupper($dbType);

        // SQLite uses INTEGER for booleans (0/1), but we'll map it as int by default
        // The application layer should handle boolean conversion
        if ($dbType === 'INTEGER') {
            return 'int';
        }

        if ($dbType === 'REAL') {
            return 'float';
        }

        if ($dbType === 'TEXT') {
            return 'string';
        }

        // Handle database types with parameters (though SQLite doesn't typically use them)
        $baseType = $this->extractBaseType($dbType);

        return $this->dbToPhp[$baseType] ?? $this->inferPhpType($baseType);
    }
}
