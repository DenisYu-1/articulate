<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;

class LockTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    /**
     * @dataProvider databaseProvider
     */
    public function testLockAddsForUpdateToSql(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('id = ?', 1)
            ->lock()
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users WHERE id = ? FOR UPDATE', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLockThrowsExceptionWhenNoTransactionInGetResult(string $databaseName): void
    {
        // Create a connection with autocommit=true to properly test transaction requirement
        $databaseNameEnv = $databaseName === 'mysql' ? 'DATABASE_NAME' : 'DATABASE_NAME';
        $hostEnv = $databaseName === 'mysql' ? 'DATABASE_HOST' : 'DATABASE_HOST_PGSQL';
        $dsn = $databaseName === 'mysql'
            ? 'mysql:host=' . getenv($hostEnv) . ';dbname=' . getenv($databaseNameEnv) . ';charset=utf8mb4'
            : 'pgsql:host=' . getenv($hostEnv) . ';port=5432;dbname=' . getenv($databaseNameEnv);

        $pdo = new \PDO($dsn, getenv('DATABASE_USER') ?? ($databaseName === 'mysql' ? 'root' : 'postgres'), getenv('DATABASE_PASSWORD'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_AUTOCOMMIT => true, // Enable autocommit to test transaction requirement
        ]);

        // Create connection wrapper that uses this PDO
        $reflection = new \ReflectionClass(Connection::class);
        $connection = $reflection->newInstanceWithoutConstructor();
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($connection, $pdo);

        $this->connection = $connection;
        $this->qb = new QueryBuilder($this->connection);

        // Clean up any existing table
        try {
            $this->connection->executeQuery('DROP TABLE IF EXISTS test_users_lock');
        } catch (\Exception $e) {
            // Ignore
        }

        // DDL operations cause implicit commits
        $this->connection->executeQuery('CREATE TABLE test_users_lock (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users_lock (id, name) VALUES (1, \'John\')');

        // Verify we're not in a transaction
        $this->assertFalse($this->connection->inTransaction(), 'Connection should not be in a transaction with autocommit=true');

        $this->expectException(TransactionRequiredException::class);
        $this->expectExceptionMessage('lock() requires an active transaction');

        $this->qb
            ->select('*')
            ->from('test_users_lock')
            ->where('id = ?', 1)
            ->lock()
            ->getResult();
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLockThrowsExceptionWhenNoTransactionInExecute(string $databaseName): void
    {
        // Create a connection with autocommit=true to properly test transaction requirement
        $databaseNameEnv = $databaseName === 'mysql' ? 'DATABASE_NAME' : 'DATABASE_NAME';
        $hostEnv = $databaseName === 'mysql' ? 'DATABASE_HOST' : 'DATABASE_HOST_PGSQL';
        $dsn = $databaseName === 'mysql'
            ? 'mysql:host=' . getenv($hostEnv) . ';dbname=' . getenv($databaseNameEnv) . ';charset=utf8mb4'
            : 'pgsql:host=' . getenv($hostEnv) . ';port=5432;dbname=' . getenv($databaseNameEnv);

        $pdo = new \PDO($dsn, getenv('DATABASE_USER') ?? ($databaseName === 'mysql' ? 'root' : 'postgres'), getenv('DATABASE_PASSWORD'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_AUTOCOMMIT => true, // Enable autocommit to test transaction requirement
        ]);

        // Create connection wrapper that uses this PDO
        $reflection = new \ReflectionClass(Connection::class);
        $connection = $reflection->newInstanceWithoutConstructor();
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($connection, $pdo);

        $this->connection = $connection;
        $this->qb = new QueryBuilder($this->connection);

        // Clean up any existing table
        try {
            $this->connection->executeQuery('DROP TABLE IF EXISTS test_users_lock_exec');
        } catch (\Exception $e) {
            // Ignore
        }

        // DDL operations cause implicit commits
        $this->connection->executeQuery('CREATE TABLE test_users_lock_exec (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users_lock_exec (id, name) VALUES (1, \'John\')');

        // Verify we're not in a transaction
        $this->assertFalse($this->connection->inTransaction(), 'Connection should not be in a transaction with autocommit=true');

        $this->expectException(TransactionRequiredException::class);
        $this->expectExceptionMessage('lock() requires an active transaction');

        $this->qb
            ->from('test_users_lock_exec')
            ->where('id = ?', 1)
            ->lock()
            ->execute();
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLockWorksWithTransactionInGetResult(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users (id, name) VALUES (1, \'John\'), (2, \'Jane\')');

        $this->connection->beginTransaction();

        try {
            $result = $this->qb
                ->select('*')
                ->from('test_users')
                ->where('id = ?', 1)
                ->lock()
                ->getResult();

            $this->assertEquals([['id' => 1, 'name' => 'John']], $result);
        } finally {
            $this->connection->rollbackTransaction();
        }
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLockWorksWithTransactionInExecute(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users (id, name) VALUES (1, \'John\'), (2, \'Jane\')');

        $this->connection->beginTransaction();

        try {
            $rowCount = $this->qb
                ->from('test_users')
                ->where('id = ?', 1)
                ->lock()
                ->execute();

            $this->assertEquals(1, $rowCount);
        } finally {
            $this->connection->rollbackTransaction();
        }
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLockWithComplexQuery(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('u.id', 'u.name')
            ->from('users', 'u')
            ->join('posts p', 'p.user_id = u.id')
            ->where('u.active = ?', true)
            ->orderBy('u.name')
            ->limit(10)
            ->lock()
            ->getSQL();

        $expected = 'SELECT u.id, u.name FROM users u JOIN posts p ON p.user_id = u.id WHERE u.active = ? ORDER BY u.name ASC LIMIT 10 FOR UPDATE';
        $this->assertEquals($expected, $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLockIsResetByResetMethod(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $qb = $this->qb
            ->select('id')
            ->from('users')
            ->lock();

        $sqlWithLock = $qb->getSQL();
        $this->assertStringContainsString('FOR UPDATE', $sqlWithLock);

        $qb->reset();

        $sqlAfterReset = $qb->getSQL();
        $this->assertStringNotContainsString('FOR UPDATE', $sqlAfterReset);
    }
}
