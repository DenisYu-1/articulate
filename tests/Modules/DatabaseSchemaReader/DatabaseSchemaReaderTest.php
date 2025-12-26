<?php

namespace Articulate\Tests\Modules\DatabaseSchemaReader;

use Articulate\Connection;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReader;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaReaderTest extends TestCase
{
    public function testMapsIndexesFromShowIndexes(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn([
            ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => 0],
            ['Key_name' => 'idx_name', 'Column_name' => 'name', 'Non_unique' => 1],
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willReturn($statement);

        $reader = new DatabaseSchemaReader($connection);

        $indexes = $reader->getTableIndexes('test_table');

        $this->assertArrayHasKey('PRIMARY', $indexes);
        $this->assertSame(['id'], $indexes['PRIMARY']['columns']);
        $this->assertTrue($indexes['PRIMARY']['unique']);

        $this->assertArrayHasKey('idx_name', $indexes);
        $this->assertSame(['name'], $indexes['idx_name']['columns']);
        $this->assertFalse($indexes['idx_name']['unique']);
    }
}
