<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Modules\Database\SchemaComparator\Comparators\IndexComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\MappingTableComparator;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;
use PHPUnit\Framework\TestCase;

class MappingTableComparatorTest extends TestCase
{
    private MappingTableComparator $comparator;
    private DatabaseSchemaReaderInterface $databaseSchemaReader;
    private SchemaNaming $schemaNaming;
    private IndexComparator $indexComparator;

    protected function setUp(): void
    {
        $this->databaseSchemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $this->schemaNaming = new SchemaNaming();
        $this->indexComparator = new IndexComparator();

        $this->comparator = new MappingTableComparator(
            $this->databaseSchemaReader,
            $this->schemaNaming,
            $this->indexComparator
        );
    }

    public function testMappingTableComparatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(MappingTableComparator::class, $this->comparator);
    }

    // ===== compareManyToManyTable Tests =====

    public function testCompareManyToManyTableCreatesNewTable(): void
    {
        $definition = [
            'tableName' => 'user_roles',
            'ownerTable' => 'users',
            'targetTable' => 'roles',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'role_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['user_id', 'role_id'],
        ];

        $existingTables = ['users', 'roles']; // Table doesn't exist

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals('user_roles', $result->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->operation);

        // Should have columns for the join table
        $this->assertCount(2, $result->columns);
        $columnNames = array_map(fn($col) => $col->name, $result->columns);
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('role_id', $columnNames);

        // Should have foreign keys
        $this->assertCount(2, $result->foreignKeys);
        $fkNames = array_map(fn($fk) => $fk->name, $result->foreignKeys);
        $this->assertContains('fk_user_roles_users_user_id', $fkNames);
        $this->assertContains('fk_user_roles_roles_role_id', $fkNames);

        // Should have primary columns
        $this->assertEquals(['user_id', 'role_id'], $result->primaryColumns);
    }

    public function testCompareManyToManyTableCreatesNewTableWithExtraProperties(): void
    {
        $definition = [
            'tableName' => 'user_permissions',
            'ownerTable' => 'users',
            'targetTable' => 'permissions',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'permission_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [
                new MappingTableProperty('created_at', 'datetime', true, null, null),
                new MappingTableProperty('updated_at', 'datetime', true, null, null),
            ],
            'primaryColumns' => ['user_id', 'permission_id'],
        ];

        $existingTables = ['users', 'permissions'];

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->operation);

        // Should have 4 columns: 2 join columns + 2 extra
        $this->assertCount(4, $result->columns);
        $columnNames = array_map(fn($col) => $col->name, $result->columns);
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('permission_id', $columnNames);
        $this->assertContains('created_at', $columnNames);
        $this->assertContains('updated_at', $columnNames);
    }

    public function testCompareManyToManyTableUpdatesExistingTable(): void
    {
        $definition = [
            'tableName' => 'user_roles',
            'ownerTable' => 'users',
            'targetTable' => 'roles',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'role_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [
                new MappingTableProperty('created_at', 'datetime', true, null, null),
            ],
            'primaryColumns' => ['user_id', 'role_id'],
        ];

        $existingTables = ['user_roles', 'users', 'roles'];

        // Mock existing table structure
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'user_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'role_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                // Missing created_at column
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_user_roles_users_user_id' => [
                    'column' => 'user_id',
                    'referencedTable' => 'users',
                    'referencedColumn' => 'id',
                ],
                'fk_user_roles_roles_role_id' => [
                    'column' => 'role_id',
                    'referencedTable' => 'roles',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([]);

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have one column to create (created_at)
        $this->assertCount(1, $result->columns);
        $this->assertEquals('created_at', $result->columns[0]->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->columns[0]->operation);

        // Foreign keys should be unchanged (no changes needed)
        $this->assertEmpty($result->foreignKeys);

        // No indexes to add/remove
        $this->assertEmpty($result->indexes);
    }

    public function testCompareManyToManyTableRemovesExtraColumns(): void
    {
        $definition = [
            'tableName' => 'user_roles',
            'ownerTable' => 'users',
            'targetTable' => 'roles',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'role_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['user_id', 'role_id'],
        ];

        $existingTables = ['user_roles', 'users', 'roles'];

        // Mock existing table with extra column
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'user_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'role_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'extra_column', 'type' => 'varchar', 'isNullable' => true, 'defaultValue' => 'default', 'length' => 100],
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_user_roles_users_user_id' => [
                    'column' => 'user_id',
                    'referencedTable' => 'users',
                    'referencedColumn' => 'id',
                ],
                'fk_user_roles_roles_role_id' => [
                    'column' => 'role_id',
                    'referencedTable' => 'roles',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([]);

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have one column to delete
        $this->assertCount(1, $result->columns);
        $this->assertEquals('extra_column', $result->columns[0]->name);
        $this->assertEquals(CompareResult::OPERATION_DELETE, $result->columns[0]->operation);
    }

    public function testCompareManyToManyTableUpdatesColumnProperties(): void
    {
        $definition = [
            'tableName' => 'user_roles',
            'ownerTable' => 'users',
            'targetTable' => 'roles',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'role_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [
                new MappingTableProperty('created_at', 'datetime', false, null, null), // created_at - not nullable
            ],
            'primaryColumns' => ['user_id', 'role_id'],
        ];

        $existingTables = ['user_roles', 'users', 'roles'];

        // Mock existing table with different column properties
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'user_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'role_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'created_at', 'type' => 'datetime', 'isNullable' => true, 'defaultValue' => null, 'length' => null], // Different nullable
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_user_roles_users_user_id' => [
                    'column' => 'user_id',
                    'referencedTable' => 'users',
                    'referencedColumn' => 'id',
                ],
                'fk_user_roles_roles_role_id' => [
                    'column' => 'role_id',
                    'referencedTable' => 'roles',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([]);

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have one column to update
        $this->assertCount(1, $result->columns);
        $this->assertEquals('created_at', $result->columns[0]->name);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->columns[0]->operation);
        $this->assertFalse($result->columns[0]->isNullableMatch);
    }

    public function testCompareManyToManyTableReturnsUpdateWhenNoChanges(): void
    {
        $definition = [
            'tableName' => 'user_roles',
            'ownerTable' => 'users',
            'targetTable' => 'roles',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'role_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['user_id', 'role_id'],
        ];

        $existingTables = ['user_roles', 'users', 'roles'];

        // Mock existing table that matches definition exactly
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'user_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'role_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_user_roles_users_user_id' => [
                    'column' => 'user_id',
                    'referencedTable' => 'users',
                    'referencedColumn' => 'id',
                ],
                'fk_user_roles_roles_role_id' => [
                    'column' => 'role_id',
                    'referencedTable' => 'roles',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([]);

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertEmpty($result->columns); // No column changes
        $this->assertEmpty($result->foreignKeys); // No FK changes
        $this->assertEmpty($result->indexes); // No index changes
        $this->assertEquals(['user_id', 'role_id'], $result->primaryColumns);
    }

    // ===== compareMorphToManyTable Tests =====

    public function testCompareMorphToManyTableCreatesNewTable(): void
    {
        $definition = [
            'tableName' => 'taggables',
            'morphName' => 'taggable',
            'typeColumn' => 'taggable_type',
            'idColumn' => 'taggable_id',
            'targetColumn' => 'tag_id',
            'targetTable' => 'tags',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['id'],
            'relations' => [],
        ];

        $existingTables = ['tags'];

        $result = $this->comparator->compareMorphToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals('taggables', $result->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->operation);

        // Should have columns: id, taggable_type, taggable_id, tag_id
        $this->assertCount(4, $result->columns);
        $columnNames = array_map(fn($col) => $col->name, $result->columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('taggable_type', $columnNames);
        $this->assertContains('taggable_id', $columnNames);
        $this->assertContains('tag_id', $columnNames);

        // Should have one foreign key to target table
        $this->assertCount(1, $result->foreignKeys);
        $this->assertEquals('fk_taggables_tags_tag_id', $result->foreignKeys[0]->name);

        // Should have one index
        $this->assertCount(1, $result->indexes);
        $this->assertEquals('taggable_type_taggable_id_index', $result->indexes[0]->name);

        // Should have primary columns
        $this->assertEquals(['id'], $result->primaryColumns);
    }

    public function testCompareMorphToManyTableCreatesNewTableWithExtraProperties(): void
    {
        $definition = [
            'tableName' => 'commentables',
            'morphName' => 'commentable',
            'typeColumn' => 'commentable_type',
            'idColumn' => 'commentable_id',
            'targetColumn' => 'comment_id',
            'targetTable' => 'comments',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [
                new MappingTableProperty('notes', 'text', true, null, null),
                new MappingTableProperty('priority', 'int', false, null, 0),
            ],
            'primaryColumns' => ['id'],
            'relations' => [],
        ];

        $existingTables = ['comments'];

        $result = $this->comparator->compareMorphToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->operation);

        // Should have 6 columns: id, type, id, target, notes, priority
        $this->assertCount(6, $result->columns);
        $columnNames = array_map(fn($col) => $col->name, $result->columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('commentable_type', $columnNames);
        $this->assertContains('commentable_id', $columnNames);
        $this->assertContains('comment_id', $columnNames);
        $this->assertContains('notes', $columnNames);
        $this->assertContains('priority', $columnNames);
    }

    public function testCompareMorphToManyTableUpdatesExistingTable(): void
    {
        $definition = [
            'tableName' => 'taggables',
            'morphName' => 'taggable',
            'typeColumn' => 'taggable_type',
            'idColumn' => 'taggable_id',
            'targetColumn' => 'tag_id',
            'targetTable' => 'tags',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [
                new MappingTableProperty('created_at', 'datetime', true, null, null),
            ],
            'primaryColumns' => ['id'],
            'relations' => [],
        ];

        $existingTables = ['taggables', 'tags'];

        // Mock existing table structure
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'taggable_type', 'type' => 'string', 'isNullable' => false, 'defaultValue' => null, 'length' => 255],
                (object)['name' => 'taggable_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'tag_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                // Missing created_at
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_taggables_tags_tag_id' => [
                    'column' => 'tag_id',
                    'referencedTable' => 'tags',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([
                'taggable_type_taggable_id_index' => [
                    'columns' => ['taggable_type', 'taggable_id'],
                    'unique' => false,
                ],
            ]);

        $result = $this->comparator->compareMorphToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have one column to create (created_at)
        $this->assertCount(1, $result->columns);
        $this->assertEquals('created_at', $result->columns[0]->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->columns[0]->operation);

        // No foreign key changes
        $this->assertEmpty($result->foreignKeys);

        // No index changes
        $this->assertEmpty($result->indexes);
    }

    public function testCompareMorphToManyTableReturnsUpdateWhenNoChanges(): void
    {
        $definition = [
            'tableName' => 'taggables',
            'morphName' => 'taggable',
            'typeColumn' => 'taggable_type',
            'idColumn' => 'taggable_id',
            'targetColumn' => 'tag_id',
            'targetTable' => 'tags',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['id'],
            'relations' => [],
        ];

        $existingTables = ['taggables', 'tags'];

        // Mock existing table that matches definition exactly
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'taggable_type', 'type' => 'string', 'isNullable' => false, 'defaultValue' => null, 'length' => 255],
                (object)['name' => 'taggable_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'tag_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_taggables_tags_tag_id' => [
                    'column' => 'tag_id',
                    'referencedTable' => 'tags',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([
                'taggable_type_taggable_id_index' => [
                    'columns' => ['taggable_type', 'taggable_id'],
                    'unique' => false,
                ],
            ]);

        $result = $this->comparator->compareMorphToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertEmpty($result->columns); // No column changes
        $this->assertEmpty($result->foreignKeys); // No FK changes
        $this->assertEmpty($result->indexes); // No index changes
        $this->assertEquals(['id'], $result->primaryColumns);
    }

    public function testCompareMorphToManyTableHandlesColumnUpdates(): void
    {
        $definition = [
            'tableName' => 'taggables',
            'morphName' => 'taggable',
            'typeColumn' => 'taggable_type',
            'idColumn' => 'taggable_id',
            'targetColumn' => 'tag_id',
            'targetTable' => 'tags',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [
                new MappingTableProperty('created_at', 'datetime', false, null, null), // created_at - not nullable
            ],
            'primaryColumns' => ['id'],
            'relations' => [],
        ];

        $existingTables = ['taggables', 'tags'];

        // Mock existing table with nullable created_at
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'taggable_type', 'type' => 'string', 'isNullable' => false, 'defaultValue' => null, 'length' => 255],
                (object)['name' => 'taggable_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'tag_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'created_at', 'type' => 'datetime', 'isNullable' => true, 'defaultValue' => null, 'length' => null], // Different
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_taggables_tags_tag_id' => [
                    'column' => 'tag_id',
                    'referencedTable' => 'tags',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([
                'taggable_type_taggable_id_index' => [
                    'columns' => ['taggable_type', 'taggable_id'],
                    'unique' => false,
                ],
            ]);

        $result = $this->comparator->compareMorphToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have one column to update
        $this->assertCount(1, $result->columns);
        $this->assertEquals('created_at', $result->columns[0]->name);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->columns[0]->operation);
        $this->assertFalse($result->columns[0]->isNullableMatch);
    }

    // ===== Edge Cases and Additional Tests =====

    public function testCompareManyToManyTableWithMissingForeignKeys(): void
    {
        $definition = [
            'tableName' => 'user_roles',
            'ownerTable' => 'users',
            'targetTable' => 'roles',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'role_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['user_id', 'role_id'],
        ];

        $existingTables = ['user_roles', 'users', 'roles'];

        // Mock existing table missing foreign keys
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'user_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'role_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([]); // No foreign keys

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([]);

        $result = $this->comparator->compareManyToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have 2 foreign keys to create
        $this->assertCount(2, $result->foreignKeys);
        $fkNames = array_map(fn($fk) => $fk->name, $result->foreignKeys);
        $this->assertContains('fk_user_roles_users_user_id', $fkNames);
        $this->assertContains('fk_user_roles_roles_role_id', $fkNames);
        $this->assertContains(CompareResult::OPERATION_CREATE, array_map(fn($fk) => $fk->operation, $result->foreignKeys));
    }

    public function testCompareMorphToManyTableWithMissingIndex(): void
    {
        $definition = [
            'tableName' => 'taggables',
            'morphName' => 'taggable',
            'typeColumn' => 'taggable_type',
            'idColumn' => 'taggable_id',
            'targetColumn' => 'tag_id',
            'targetTable' => 'tags',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['id'],
            'relations' => [],
        ];

        $existingTables = ['taggables', 'tags'];

        // Mock existing table missing the morph index
        $this->databaseSchemaReader->method('getTableColumns')
            ->willReturn([
                (object)['name' => 'id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'taggable_type', 'type' => 'string', 'isNullable' => false, 'defaultValue' => null, 'length' => 255],
                (object)['name' => 'taggable_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
                (object)['name' => 'tag_id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
            ]);

        $this->databaseSchemaReader->method('getTableForeignKeys')
            ->willReturn([
                'fk_taggables_tags_tag_id' => [
                    'column' => 'tag_id',
                    'referencedTable' => 'tags',
                    'referencedColumn' => 'id',
                ],
            ]);

        $this->databaseSchemaReader->method('getTableIndexes')
            ->willReturn([]); // No indexes

        $result = $this->comparator->compareMorphToManyTable($definition, $existingTables);

        $this->assertInstanceOf(TableCompareResult::class, $result);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Should have one index to create
        $this->assertCount(1, $result->indexes);
        $this->assertEquals('taggable_type_taggable_id_index', $result->indexes[0]->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->indexes[0]->operation);
        $this->assertEquals(['taggable_type', 'taggable_id'], $result->indexes[0]->columns);
        $this->assertFalse($result->indexes[0]->isUnique);
    }
}