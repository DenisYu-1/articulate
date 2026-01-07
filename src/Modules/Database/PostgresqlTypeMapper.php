<?php

namespace Articulate\Modules\Database;

use Articulate\Utils\TypeRegistry;

/**
 * PostgreSQL-specific type mapper with PostgreSQL-specific type mappings.
 */
class PostgresqlTypeMapper extends TypeRegistry {
    protected function registerBuiltInTypes(): void
    {
        // Basic types (register non-nullable first) - using PostgreSQL-specific syntax
        $this->registerType('int', 'INTEGER');
        $this->registerType('float', 'DOUBLE PRECISION');
        $this->registerType('string', 'VARCHAR(255)');
        $this->registerType('bool', 'BOOLEAN');
        $this->registerType('mixed', 'TEXT');

        // PostgreSQL uses native BOOLEAN type, no special converter needed

        // DateTime types
        $this->registerType('DateTime', 'TIMESTAMP');
        $this->registerType('DateTimeImmutable', 'TIMESTAMP');

        // PostgreSQL-specific types
        $this->registerType('uuid', 'UUID');
        $this->registerType('json', 'JSONB');

        // Class/Interface mappings
        $this->registerClassMapping(\DateTimeInterface::class, 'TIMESTAMP', null, 10); // High priority for DateTime

        // Nullable versions (these override the db->php mapping for reverse lookups)
        $this->registerType('?int', 'INTEGER');
        $this->registerType('?float', 'DOUBLE PRECISION');
        $this->registerType('?string', 'VARCHAR(255)');
        $this->registerType('?bool', 'BOOLEAN');
        $this->registerType('?DateTime', 'TIMESTAMP');
        $this->registerType('?DateTimeImmutable', 'TIMESTAMP');
        $this->registerType('?uuid', 'UUID');
        $this->registerType('?json', 'JSONB');
    }

    public function getPhpType(string $dbType): string
    {
        $dbType = strtoupper($dbType);

        // PostgreSQL-specific type mappings
        if ($dbType === 'BOOLEAN') {
            return 'bool';
        }

        if ($dbType === 'UUID') {
            return 'string';
        }

        if (in_array($dbType, ['JSON', 'JSONB'])) {
            return 'mixed';
        }

        // Handle SERIAL types (auto-incrementing integers)
        if (in_array($dbType, ['SERIAL', 'BIGSERIAL', 'SMALLSERIAL'])) {
            return 'int';
        }

        // Handle database types with parameters like VARCHAR(255), NUMERIC(10,2)
        $baseType = $this->extractBaseType($dbType);

        return $this->dbToPhp[$baseType] ?? $this->inferPhpType($baseType);
    }
}
