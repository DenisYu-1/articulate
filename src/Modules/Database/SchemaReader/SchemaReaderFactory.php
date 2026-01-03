<?php

namespace Articulate\Modules\Database\SchemaReader;

use Articulate\Connection;
use InvalidArgumentException;

class SchemaReaderFactory {
    public static function create(Connection $connection): DatabaseSchemaReaderInterface
    {
        return match ($connection->getDriverName()) {
            Connection::MYSQL => new MySqlSchemaReader($connection),
            default => throw new InvalidArgumentException("Unsupported database driver: {$connection->getDriverName()}"),
        };
    }
}
