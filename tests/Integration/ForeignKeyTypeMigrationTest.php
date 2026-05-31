<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\MySqlTypeMapper;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaReader\SchemaReaderFactory;
use Articulate\Modules\Migrations\Generator\MySqlMigrationGenerator;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\FkIntChild;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\FkIntParent;

/**
 * Verifies that the full diff→generate→execute pipeline produces FK columns
 * whose type (INT UNSIGNED) matches the referenced PK column type, avoiding
 * MySQL error 3780.
 */
class ForeignKeyTypeMigrationTest extends AbstractTestCase {
    protected function setUpTestTables(\Articulate\Connection $connection, string $databaseName): bool
    {
        if ($databaseName !== 'mysql') {
            return true;
        }

        $connection->executeQuery('DROP TABLE IF EXISTS `fk_int_child`');
        $connection->executeQuery('DROP TABLE IF EXISTS `fk_int_parent`');

        return true;
    }

    protected function tearDownTestTables(\Articulate\Connection $connection, string $databaseName): void
    {
        if ($databaseName !== 'mysql') {
            return;
        }

        $connection->executeQuery('DROP TABLE IF EXISTS `fk_int_child`');
        $connection->executeQuery('DROP TABLE IF EXISTS `fk_int_parent`');
    }

    public function testForeignKeyIntColumnMatchesPrimaryKeyType(): void
    {
        $connection = $this->getConnection('mysql');

        $parent = new ReflectionEntity(FkIntParent::class);
        $child  = new ReflectionEntity(FkIntChild::class);

        $reader     = SchemaReaderFactory::create($connection);
        $comparator = new DatabaseSchemaComparator($reader, new SchemaNaming());
        $generator  = new MySqlMigrationGenerator(new MySqlTypeMapper());

        $results = iterator_to_array($comparator->compareAll([$parent, $child]));

        // Sort: parent table first so FK reference is valid on execute
        usort($results, fn($a, $b) => $a->name === 'fk_int_parent' ? -1 : 1);

        foreach ($results as $result) {
            $sql = $generator->generate($result);
            // Executing here fails with MySQL error 3780 if FK type != PK type
            $connection->executeQuery($sql);
        }

        // Verify column types directly in the schema
        $parentColumns = $connection->executeQuery('SHOW COLUMNS FROM `fk_int_parent`')->fetchAll();
        $childColumns  = $connection->executeQuery('SHOW COLUMNS FROM `fk_int_child`')->fetchAll();

        $parentMap = array_column($parentColumns, null, 'Field');
        $childMap  = array_column($childColumns,  null, 'Field');

        $this->assertEquals('int unsigned', $parentMap['id']['Type'], 'PK should be INT UNSIGNED');
        $this->assertEquals('int unsigned', $childMap['parent_id']['Type'], 'FK should be INT UNSIGNED to match PK');
    }
}
