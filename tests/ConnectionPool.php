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

    private ?string $lastMysqlError = null;

    private ?string $lastPgsqlError = null;

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

        $databaseName = getenv('DATABASE_NAME') ?: 'articulate_test';
        $mysqlHost = getenv('DATABASE_HOST') ?: '127.0.0.1';
        $pgHost = getenv('DATABASE_HOST_PGSQL') ?: '127.0.0.1';
        $mysqlUser = getenv('DATABASE_USER') ?: 'root';
        $pgsqlUser = getenv('DATABASE_USER_PGSQL') ?: $mysqlUser;
        $databasePassword = getenv('DATABASE_PASSWORD') ?: '';
        $pgsqlPassword = getenv('DATABASE_PASSWORD_PGSQL') ?: $databasePassword;
        $pgsqlPort = getenv('DATABASE_PORT_PGSQL') ?: '5432';

        if ($this->mysqlConnection === null) {
            try {
                $this->mysqlConnection = new Connection(
                    'mysql:host=' . $mysqlHost . ';dbname=' . $databaseName . ';charset=utf8mb4',
                    $mysqlUser,
                    $databasePassword
                );
            } catch (Exception $e) {
                $this->lastMysqlError = $e->getMessage();
                $this->mysqlConnection = null;
            }
        }

        if ($this->pgsqlConnection === null) {
            try {
                $this->pgsqlConnection = new Connection(
                    'pgsql:host=' . $pgHost . ';port=' . $pgsqlPort . ';dbname=' . $databaseName,
                    $pgsqlUser,
                    $pgsqlPassword
                );
            } catch (Exception $e) {
                $this->lastPgsqlError = $e->getMessage();
                $this->pgsqlConnection = null;
            }
        }
    }

    public function getLastMysqlConnectionError(): ?string
    {
        return $this->lastMysqlError;
    }

    public function getLastPgsqlConnectionError(): ?string
    {
        return $this->lastPgsqlError;
    }
}
