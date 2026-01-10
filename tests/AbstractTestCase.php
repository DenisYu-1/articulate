<?php

namespace Articulate\Tests;

use Articulate\Connection;
use Dotenv\Dotenv;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class AbstractTestCase extends TestCase {
    protected ?Connection $mysqlConnection = null;

    protected ?Connection $pgsqlConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Try to find .env file in different possible locations
        $possiblePaths = [
            __DIR__ . '/../.env',           // tests/../.env (normal case)
            __DIR__ . '/../../.env',        // tests/../../.env (vendor case)
            __DIR__ . '/../../../.env',     // tests/../../../.env (deep vendor case)
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $dotenv = Dotenv::createImmutable(dirname($path));
                $dotenv->load();

                break;
            }
        }

        $databaseName = $this->getDatabaseName();

        // Initialize connections, catching exceptions for unavailable databases
        try {
            $this->mysqlConnection = new Connection('mysql:host=' . (getenv('DATABASE_HOST')) . ';dbname=' . $databaseName . ';charset=utf8mb4', getenv('DATABASE_USER') ?? 'root', getenv('DATABASE_PASSWORD'));
            $this->mysqlConnection->beginTransaction();
        } catch (Exception $e) {
            $this->mysqlConnection = null;
        }

        try {
            $this->pgsqlConnection = new Connection('pgsql:host=' . getenv('DATABASE_HOST_PGSQL') . ';port=5432;dbname=' . $databaseName, getenv('DATABASE_USER') ?? 'postgres', getenv('DATABASE_PASSWORD'));
            $this->pgsqlConnection->beginTransaction();
        } catch (Exception $e) {
            $this->pgsqlConnection = null;
        }
    }

    protected function tearDown(): void
    {
        if ($this->pgsqlConnection) {
            try {
                $this->pgsqlConnection->rollbackTransaction();
            } catch (Exception $e) {
                // Ignore rollback errors
            }
        }

        if ($this->mysqlConnection) {
            try {
                $this->mysqlConnection->rollbackTransaction();
            } catch (Exception $e) {
                // Ignore rollback errors
            }
        }

        unset($this->mysqlConnection, $this->pgsqlConnection);
        parent::tearDown();
    }

    protected function getDatabaseName(): string
    {
        return getenv('DATABASE_NAME');
    }

    /**
     * Get a database connection by name.
     */
    protected function getConnection(string $databaseName): Connection
    {
        return match ($databaseName) {
            'mysql' => $this->mysqlConnection ?? throw new RuntimeException('MySQL connection not available'),
            'pgsql' => $this->pgsqlConnection ?? throw new RuntimeException('PostgreSQL connection not available'),
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}")
        };
    }

    /**
     * Check if a specific database is available.
     */
    protected function isDatabaseAvailable(string $databaseName): bool
    {
        try {
            $this->getConnection($databaseName);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
