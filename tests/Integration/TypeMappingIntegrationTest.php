<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReader;
use Articulate\Modules\Migrations\Generator\MySqlMigrationGenerator;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestBoolEntity;

/**
 * Integration test to verify type mapping works end-to-end.
 */
class TypeMappingIntegrationTest extends AbstractTestCase
{
    public function testBoolTypeMappingInMigrations(): void
    {
        $this->skipIfDatabaseNotAvailable('mysql');
        $connection = $this->getConnection('mysql');

        // Clean up any existing table
        $connection->executeQuery('DROP TABLE IF EXISTS `test_bool_entity`');

        $entity = new ReflectionEntity(TestBoolEntity::class);
        $reader = new DatabaseSchemaReader($connection);
        $comparator = new DatabaseSchemaComparator($reader, new SchemaNaming());
        $generator = new MySqlMigrationGenerator();

        $compareResults = iterator_to_array($comparator->compareAll([$entity]));
        $compareResult = $compareResults[0]; // Should be the only result

        // Generate the migration SQL
        $sql = $generator->generate($compareResult);

        // Should be a CREATE TABLE since we dropped the existing table
        $this->assertStringStartsWith('CREATE TABLE', $sql, 'Should generate CREATE TABLE for new table');

        // Verify that bool properties map to TINYINT(1)
        $this->assertTrue(strpos($sql, '`is_active` TINYINT(1) NOT NULL') !== false, 'is_active should map to TINYINT(1) NOT NULL');
        $this->assertTrue(strpos($sql, '`is_featured` TINYINT(1)') !== false, 'is_featured should map to TINYINT(1)');

        // Verify other types
        $this->assertTrue(strpos($sql, '`id` INT NOT NULL') !== false, 'id should map to INT NOT NULL');
        $this->assertTrue(strpos($sql, '`name` VARCHAR(255) NOT NULL') !== false, 'name should map to VARCHAR(255) NOT NULL');

        // Execute the migration to ensure it works
        $connection->executeQuery($sql);

        // Verify the table was created with correct column types
        $columns = $connection->executeQuery('SHOW COLUMNS FROM `test_bool_entity`')->fetchAll();

        $columnMap = [];
        foreach ($columns as $column) {
            $columnMap[$column['Field']] = $column;
        }

        $this->assertEquals('tinyint(1)', $columnMap['is_active']['Type']);
        $this->assertEquals('tinyint(1)', $columnMap['is_featured']['Type']);
        $this->assertStringStartsWith('varchar(255)', $columnMap['name']['Type']);
        $this->assertEquals('int', $columnMap['id']['Type']);
    }
}
