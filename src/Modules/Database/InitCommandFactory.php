<?php

namespace Articulate\Modules\Database;

use Articulate\Connection;
use InvalidArgumentException;

class InitCommandFactory {
    public static function create(Connection $connection): InitCommandInterface
    {
        return match ($connection->getDriverName()) {
            Connection::MYSQL => new MySqlInitCommand(),
            Connection::SQLITE => new SqliteInitCommand(),
            Connection::PGSQL => new PostgresqlInitCommand(),
            default => throw new InvalidArgumentException("Unsupported database driver: {$connection->getDriverName()}"),
        };
    }
}
