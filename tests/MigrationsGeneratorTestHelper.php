<?php

namespace Articulate\Tests;

use Articulate\Connection;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;

/**
 * Test helper for creating MigrationsCommandGenerator instances with forced database drivers.
 * This separates test-only functionality from production code.
 */
class MigrationsGeneratorTestHelper {
    public static function forMySql(): MigrationsCommandGenerator
    {
        // Create a test connection with forced MySQL driver
        $connection = new Connection('sqlite::memory:', '', '');

        return new MigrationsCommandGenerator($connection, Connection::MYSQL);
    }

    public static function forPostgresql(): MigrationsCommandGenerator
    {
        // Create a test connection with forced PostgreSQL driver
        $connection = new Connection('sqlite::memory:', '', '');

        return new MigrationsCommandGenerator($connection, Connection::PGSQL);
    }
}
