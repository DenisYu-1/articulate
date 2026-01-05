<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestCommentEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMorphToEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPostEntity;

/**
 * Integration test for polymorphic relations end-to-end functionality.
 */
class PolymorphicRelationsIntegrationTest extends AbstractTestCase {
    public function testPolymorphicRelationsEndToEnd()
    {
        // Create mock schema reader that returns empty database
        $schemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $schemaReader->method('getTables')->willReturn([]);
        $schemaReader->method('getTableColumns')->willReturn([]);
        $schemaReader->method('getTableIndexes')->willReturn([]);
        $schemaReader->method('getTableForeignKeys')->willReturn([]);

        $schemaComparator = new DatabaseSchemaComparator($schemaReader, new SchemaNaming());

        // Use the actual test entities we created
        $pollEntity = new ReflectionEntity(TestMorphToEntity::class);
        $postEntity = new ReflectionEntity(TestPostEntity::class);
        $commentEntity = new ReflectionEntity(TestCommentEntity::class);

        $results = iterator_to_array($schemaComparator->compareAll([$pollEntity, $postEntity, $commentEntity]));

        // Should generate 3 tables: poll, post, comment
        $this->assertCount(3, $results);

        // Find the poll table result
        $pollTable = null;
        foreach ($results as $result) {
            if ($result->name === 'test_morph_to_entity') {
                $pollTable = $result;

                break;
            }
        }

        $this->assertNotNull($pollTable, 'Poll table should be generated');
        $this->assertEquals('create', $pollTable->operation);

        // Poll table should have 4 columns: id, title, pollable_type, pollable_id
        $this->assertCount(4, $pollTable->columns);

        $columnNames = array_map(fn ($col) => $col->name, $pollTable->columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('title', $columnNames);
        $this->assertContains('pollable_type', $columnNames);
        $this->assertContains('pollable_id', $columnNames);

        // Should have 1 index for the polymorphic columns
        $this->assertCount(1, $pollTable->indexes);
        $this->assertEquals('pollable_morph_index', $pollTable->indexes[0]->name);
        $this->assertEquals(['pollable_type', 'pollable_id'], $pollTable->indexes[0]->columns);

        // Should have no foreign keys (polymorphic relations don't create FKs)
        $this->assertCount(0, $pollTable->foreignKeys);
    }

    public function testPolymorphicRelationsWithExistingSchema()
    {
        // Test updating existing schema to add polymorphic columns
        $existingColumns = [
            'id' => (object) ['name' => 'id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
            'title' => (object) ['name' => 'title', 'type' => 'string', 'isNullable' => false, 'defaultValue' => null, 'length' => 255],
        ];

        $schemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $schemaReader->method('getTables')->willReturn(['test_morph_to_entity']);
        $schemaReader->method('getTableColumns')->willReturnCallback(function ($table) use ($existingColumns) {
            return $table === 'test_morph_to_entity' ? array_values($existingColumns) : [];
        });
        $schemaReader->method('getTableIndexes')->willReturn([]);
        $schemaReader->method('getTableForeignKeys')->willReturn([]);

        $schemaComparator = new DatabaseSchemaComparator($schemaReader, new SchemaNaming());

        $pollEntity = new ReflectionEntity(TestMorphToEntity::class);
        $results = iterator_to_array($schemaComparator->compareAll([$pollEntity]));

        $this->assertCount(1, $results);
        $this->assertEquals('update', $results[0]->operation);

        // Should add 2 new columns: pollable_type, pollable_id
        $this->assertCount(2, $results[0]->columns);
        $this->assertEquals('pollable_type', $results[0]->columns[0]->name);
        $this->assertEquals('create', $results[0]->columns[0]->operation);
        $this->assertEquals('pollable_id', $results[0]->columns[1]->name);
        $this->assertEquals('create', $results[0]->columns[1]->operation);

        // Should add 1 index
        $this->assertCount(1, $results[0]->indexes);
        $this->assertEquals('pollable_morph_index', $results[0]->indexes[0]->name);
    }
}
