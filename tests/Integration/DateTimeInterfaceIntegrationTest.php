<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Modules\MigrationsGenerator\MySqlMigrationGenerator;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestDateTimeEntity;

/**
 * Integration test to verify DateTimeInterface support works end-to-end.
 */
class DateTimeInterfaceIntegrationTest extends AbstractTestCase
{
    public function testDateTimeInterfaceMappingInMigrations(): void
    {
        $this->skipIfDatabaseNotAvailable('mysql');
        $connection = $this->getConnection('mysql');

        $entity = new ReflectionEntity(TestDateTimeEntity::class);
        $reader = new DatabaseSchemaReader($connection);
        $comparator = new DatabaseSchemaComparator($reader, new SchemaNaming());
        $generator = new MySqlMigrationGenerator();

        $compareResults = iterator_to_array($comparator->compareAll([$entity]));
        $compareResult = $compareResults[0]; // Should be the only result

        // Generate the migration SQL
        $sql = $generator->generate($compareResult);

        // Verify that all DateTime types map to DATETIME
        $this->assertTrue(strpos($sql, '`created_at` DATETIME NOT NULL') !== false, 'DateTime should map to DATETIME');
        $this->assertTrue(strpos($sql, '`updated_at` DATETIME NOT NULL') !== false, 'DateTimeImmutable should map to DATETIME');
        $this->assertTrue(strpos($sql, '`published_at` DATETIME') !== false, 'DateTimeInterface should map to DATETIME');

        // Execute the migration to ensure it works
        $connection->executeQuery($sql);

        // Verify the table was created with correct column types
        $columns = $connection->executeQuery('SHOW COLUMNS FROM `test_date_time_entity`')->fetchAll();

        $columnMap = [];
        foreach ($columns as $column) {
            $columnMap[$column['Field']] = $column;
        }

        $this->assertEquals('datetime', $columnMap['created_at']['Type']);
        $this->assertEquals('datetime', $columnMap['updated_at']['Type']);
        $this->assertEquals('datetime', $columnMap['published_at']['Type']);
    }
}
