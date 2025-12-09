<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;

class MigrationsCommandGeneratorDatabaseTest extends AbstractTestCase
{
    public function testCreateTableWithForeignKeyAppliedToDatabase(): void
    {
        $this->sqliteConnection->executeQuery('PRAGMA foreign_keys = ON');
        $this->sqliteConnection->executeQuery('CREATE TABLE related_entity (id INTEGER PRIMARY KEY)');

        $compareResult = new TableCompareResult(
            'test_table',
            'create',
            [
                new ColumnCompareResult(
                    name: 'id',
                    operation: 'create',
                    propertyData: new PropertiesData('int', false),
                    columnData: new PropertiesData(),
                ),
                new ColumnCompareResult(
                    name: 'related_entity_id',
                    operation: 'create',
                    propertyData: new PropertiesData('int', false),
                    columnData: new PropertiesData(),
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    name: (new \Articulate\Schema\SchemaNaming())->foreignKeyName('test_table', 'related_entity', 'related_entity_id'),
                    operation: 'create',
                    column: 'related_entity_id',
                    referencedTable: 'related_entity',
                ),
            ],
        );

        $sql = (new MigrationsCommandGenerator())->generate($compareResult);
        $this->sqliteConnection->executeQuery($sql);

        $columns = $this->sqliteConnection->executeQuery("PRAGMA table_info('test_table')")->fetchAll();
        $this->assertSame(['id', 'related_entity_id'], array_column($columns, 'name'));

        $foreignKeys = $this->sqliteConnection->executeQuery("PRAGMA foreign_key_list('test_table')")->fetchAll();
        $this->assertCount(1, $foreignKeys);
        $this->assertSame('related_entity', $foreignKeys[0]['table']);
        $this->assertSame('related_entity_id', $foreignKeys[0]['from']);
    }
}

