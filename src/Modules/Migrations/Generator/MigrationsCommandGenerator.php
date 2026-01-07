<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Connection;
use Articulate\Modules\Database\MySqlTypeMapper;
use Articulate\Modules\Database\PostgresqlTypeMapper;
use Articulate\Modules\Database\SqliteTypeMapper;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;

class MigrationsCommandGenerator {
    private MigrationGeneratorInterface $strategy;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?string $forcedDriver = null
    ) {
        $this->strategy = $this->createStrategy();
    }

    public static function forMySql(): self
    {
        // Create a test connection with forced MySQL driver
        $connection = new Connection('sqlite::memory:', '', '');

        return new self($connection, Connection::MYSQL);
    }

    public static function forDatabase(string $driver): self
    {
        // Create a test connection with forced driver
        $connection = new Connection('sqlite::memory:', '', '');

        return new self($connection, $driver);
    }

    private function createStrategy(): MigrationGeneratorInterface
    {
        $driverName = $this->forcedDriver ?? $this->connection->getDriverName();

        return match ($driverName) {
            Connection::MYSQL => new MySqlMigrationGenerator(new MySqlTypeMapper()),
            Connection::PGSQL => new PostgresqlMigrationGenerator(new PostgresqlTypeMapper()),
            Connection::SQLITE => new SqliteMigrationGenerator(new SqliteTypeMapper()),
            default => throw new \InvalidArgumentException("Unsupported database driver: {$driverName}"),
        };
    }

    public function generate(TableCompareResult $compareResult): string
    {
        return $this->strategy->generate($compareResult);
    }

    public function rollback(TableCompareResult $compareResult): string
    {
        return $this->strategy->rollback($compareResult);
    }
}
