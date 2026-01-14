<?php

namespace Articulate\Tests\Modules\Migrations;

use Articulate\Connection;
use Articulate\Modules\Migrations\Generator\BaseMigration;
use Articulate\Tests\AbstractTestCase;
use RuntimeException;

class BaseMigrationTest extends AbstractTestCase {
    private Connection $connection;

    private BaseMigrationTestMigration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->migration = new BaseMigrationTestMigration($this->connection);
    }

    public function testRunMigrationExecutesUpMethodWithinTransaction(): void
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('commit');

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'INSERT INTO migrations (name, executed_at, running_time) VALUES (?, ?, ?)',
                $this->callback(function ($params) {
                    return count($params) === 3 &&
                           is_string($params[0]) &&
                           is_string($params[1]) &&
                           is_int($params[2]);
                })
            );

        $this->migration->runMigration();

        $this->assertTrue($this->migration->upExecuted);
    }

    public function testRunMigrationRollsBackOnException(): void
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('rollbackTransaction');

        $this->connection->expects($this->never())
            ->method('commit');

        $this->migration->shouldThrowException = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->migration->runMigration();
    }

    public function testRollbackMigrationExecutesDownMethodWithinTransaction(): void
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('commit');

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'DELETE FROM migrations WHERE name = ?',
                [BaseMigrationTestMigration::class]
            );

        $this->migration->rollbackMigration();

        $this->assertTrue($this->migration->downExecuted);
    }

    public function testRollbackMigrationRollsBackOnException(): void
    {
        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('rollbackTransaction');

        $this->connection->expects($this->never())
            ->method('commit');

        $this->migration->shouldThrowExceptionOnDown = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test down exception');

        $this->migration->rollbackMigration();
    }

    public function testAddSqlExecutesQuery(): void
    {
        $sql = 'CREATE TABLE test (id INT)';

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with($sql);

        $this->migration->executeAddSql($sql);
    }

    public function testDownMethodIsOptional(): void
    {
        // Test that down() method can be empty (default implementation)
        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('commit');

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'DELETE FROM migrations WHERE name = ?',
                [BaseMigrationTestMinimalMigration::class]
            );

        $migration = new BaseMigrationTestMinimalMigration($this->connection);
        $migration->rollbackMigration();

        $this->assertTrue($migration->rollbackExecuted);
    }
}

/**
 * Test migration class for testing BaseMigration.
 */
class BaseMigrationTestMigration extends BaseMigration {
    public bool $upExecuted = false;

    public bool $downExecuted = false;

    public bool $shouldThrowException = false;

    public bool $shouldThrowExceptionOnDown = false;

    protected function up(): void
    {
        $this->upExecuted = true;

        if ($this->shouldThrowException) {
            throw new RuntimeException('Test exception');
        }
    }

    protected function down(): void
    {
        $this->downExecuted = true;

        if ($this->shouldThrowExceptionOnDown) {
            throw new RuntimeException('Test down exception');
        }
    }

    public function executeAddSql(string $sql): void
    {
        $this->addSql($sql);
    }
}

/**
 * Minimal migration class for testing optional down method.
 */
class BaseMigrationTestMinimalMigration extends BaseMigration {
    public bool $rollbackExecuted = false;

    protected function up(): void
    {
        // Empty implementation
    }

    public function rollbackMigration(): void
    {
        $this->rollbackExecuted = true;
        parent::rollbackMigration();
    }
}
