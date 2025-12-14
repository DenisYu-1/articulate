<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;

class MigrationsCommandGenerator
{
    public function __construct(
        private readonly MigrationGeneratorStrategy $strategy
    ) {}

    public function generate(TableCompareResult $compareResult): string
    {
        return $this->strategy->generate($compareResult);
    }

    public function rollback(TableCompareResult $compareResult): string
    {
        return $this->strategy->rollback($compareResult);
    }

    public static function forMySql(): self
    {
        return new self(new MySqlMigrationGenerator());
    }

    public static function forPostgresql(): self
    {
        return new self(new PostgresqlMigrationGenerator());
    }

    public static function forSqlite(): self
    {
        return new self(new SqliteMigrationGenerator());
    }

    public static function forDatabase(string $databaseType): self
    {
        return match ($databaseType) {
            'mysql' => self::forMySql(),
            'pgsql', 'postgresql' => self::forPostgresql(),
            'sqlite' => self::forSqlite(),
            default => throw new \InvalidArgumentException("Unsupported database type: {$databaseType}"),
        };
    }
}
