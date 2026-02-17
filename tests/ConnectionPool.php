<?php

namespace Articulate\Tests;

use Articulate\Connection;
use Dotenv\Dotenv;
use Exception;

class ConnectionPool {
    private static ?self $instance = null;

    private ?Connection $mysqlConnection = null;

    private ?Connection $pgsqlConnection = null;

    private bool $envLoaded = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getMysqlConnection(): ?Connection
    {
        if ($this->mysqlConnection === null) {
            $this->initializeConnections();
        }

        return $this->mysqlConnection;
    }

    public function getPgsqlConnection(): ?Connection
    {
        if ($this->pgsqlConnection === null) {
            $this->initializeConnections();
        }

        return $this->pgsqlConnection;
    }

    private function initializeConnections(): void
    {
        if (!$this->envLoaded) {
            $possiblePaths = [
                __DIR__ . '/../.env',
                __DIR__ . '/../../.env',
                __DIR__ . '/../../../.env',
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $dotenv = Dotenv::createImmutable(dirname($path));
                    $dotenv->load();
                    $this->envLoaded = true;

                    break;
                }
            }
        }

        $databaseName = getenv('DATABASE_NAME');

        if ($this->mysqlConnection === null) {
            try {
                $this->mysqlConnection = new Connection(
                    'mysql:host=' . (getenv('DATABASE_HOST')) . ';dbname=' . $databaseName . ';charset=utf8mb4',
                    getenv('DATABASE_USER') ?? 'root',
                    getenv('DATABASE_PASSWORD')
                );
            } catch (Exception $e) {
                $this->mysqlConnection = null;
            }
        }

        if ($this->pgsqlConnection === null) {
            try {
                $this->pgsqlConnection = new Connection(
                    'pgsql:host=' . getenv('DATABASE_HOST_PGSQL') . ';port=5432;dbname=' . $databaseName,
                    getenv('DATABASE_USER') ?? 'postgres',
                    getenv('DATABASE_PASSWORD')
                );
            } catch (Exception $e) {
                $this->pgsqlConnection = null;
            }
        }
    }
}
