<?php

namespace Articulate\Tests\Modules\Database;

use Articulate\Connection;
use Articulate\Modules\Database\InitCommandFactory;
use Articulate\Modules\Database\InitCommandInterface;
use Articulate\Modules\Database\MySqlInitCommand;
use Articulate\Modules\Database\PostgresqlInitCommand;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InitCommandTest extends TestCase {
    public function testMySqlInitCommandReturnsCorrectSql(): void
    {
        $command = new MySqlInitCommand();
        $sql = $command->getCreateMigrationsTableSql();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS migrations', $sql);
        $this->assertStringContainsString('id INT AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('name VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('executed_at DATETIME NOT NULL', $sql);
        $this->assertStringContainsString('running_time INT NOT NULL', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }

    public function testPostgresqlInitCommandReturnsCorrectSql(): void
    {
        $command = new PostgresqlInitCommand();
        $sql = $command->getCreateMigrationsTableSql();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS migrations', $sql);
        $this->assertStringContainsString('id SERIAL PRIMARY KEY', $sql);
        $this->assertStringContainsString('name VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('executed_at TIMESTAMPTZ NOT NULL', $sql);
        $this->assertStringContainsString('running_time INT NOT NULL', $sql);
    }

    public function testInitCommandFactoryCreatesMySqlCommand(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn(Connection::MYSQL);

        $command = InitCommandFactory::create($connection);

        $this->assertInstanceOf(MySqlInitCommand::class, $command);
    }

    public function testInitCommandFactoryCreatesPostgresqlCommand(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn(Connection::PGSQL);

        $command = InitCommandFactory::create($connection);

        $this->assertInstanceOf(PostgresqlInitCommand::class, $command);
    }

    public function testInitCommandFactoryThrowsExceptionForUnsupportedDriver(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn('sqlite');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: sqlite');

        InitCommandFactory::create($connection);
    }

    public function testInitCommandFactoryThrowsExceptionForUnknownDriver(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn('unknown');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: unknown');

        InitCommandFactory::create($connection);
    }

    public function testMySqlInitCommandImplementsInterface(): void
    {
        $command = new MySqlInitCommand();
        $this->assertInstanceOf(InitCommandInterface::class, $command);
    }

    public function testPostgresqlInitCommandImplementsInterface(): void
    {
        $command = new PostgresqlInitCommand();
        $this->assertInstanceOf(InitCommandInterface::class, $command);
    }

    public function testMySqlInitCommandReturnsValidSql(): void
    {
        $command = new MySqlInitCommand();
        $sql = $command->getCreateMigrationsTableSql();

        // Should be valid SQL (basic validation)
        $this->assertIsString($sql);
        $this->assertNotEmpty($sql);
        $this->assertStringStartsWith('CREATE TABLE', trim($sql));
    }

    public function testPostgresqlInitCommandReturnsValidSql(): void
    {
        $command = new PostgresqlInitCommand();
        $sql = $command->getCreateMigrationsTableSql();

        // Should be valid SQL (basic validation)
        $this->assertIsString($sql);
        $this->assertNotEmpty($sql);
        $this->assertStringStartsWith('CREATE TABLE', trim($sql));
    }
}
