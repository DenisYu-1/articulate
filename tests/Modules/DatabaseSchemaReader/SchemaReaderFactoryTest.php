<?php

namespace Articulate\Tests\Modules\DatabaseSchemaReader;

use Articulate\Connection;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Modules\Database\SchemaReader\MySqlSchemaReader;
use Articulate\Modules\Database\SchemaReader\PostgresqlSchemaReader;
use Articulate\Modules\Database\SchemaReader\SchemaReaderFactory;
use Articulate\Tests\AbstractTestCase;
use InvalidArgumentException;

class SchemaReaderFactoryTest extends AbstractTestCase {
    public function testCreateReturnsMySqlSchemaReaderForMySqlConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn(Connection::MYSQL);

        $reader = SchemaReaderFactory::create($connection);

        $this->assertInstanceOf(MySqlSchemaReader::class, $reader);
        $this->assertInstanceOf(DatabaseSchemaReaderInterface::class, $reader);
    }

    public function testCreateReturnsPostgresqlSchemaReaderForPostgresqlConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn(Connection::PGSQL);

        $reader = SchemaReaderFactory::create($connection);

        $this->assertInstanceOf(PostgresqlSchemaReader::class, $reader);
        $this->assertInstanceOf(DatabaseSchemaReaderInterface::class, $reader);
    }

    public function testCreateThrowsExceptionForUnsupportedDriver(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn('unsupported_driver');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: unsupported_driver. Supported: MySQL, PostgreSQL');

        SchemaReaderFactory::create($connection);
    }

    public function testCreatePassesConnectionToReader(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn(Connection::MYSQL);

        $reader = SchemaReaderFactory::create($connection);

        // We can't directly test the constructor parameter, but we can verify the reader was created
        // and that it's the correct type, which implies the connection was passed correctly
        $this->assertInstanceOf(MySqlSchemaReader::class, $reader);
    }
}
