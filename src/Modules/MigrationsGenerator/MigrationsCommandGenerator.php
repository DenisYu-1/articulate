<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Connection;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;

class MigrationsCommandGenerator
{
    private MigrationGeneratorStrategy $strategy;

    public function __construct(
        private readonly Connection $connection
    ) {
        $this->strategy = $this->createStrategy();
    }

    private function createStrategy(): MigrationGeneratorStrategy
    {
        $driverName = $this->connection->getDriverName();

        return match ($driverName) {
            Connection::MYSQL => new MySqlMigrationGenerator(),
            Connection::PGSQL => new PostgresqlMigrationGenerator(),
            Connection::SQLITE => new SqliteMigrationGenerator(),
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
