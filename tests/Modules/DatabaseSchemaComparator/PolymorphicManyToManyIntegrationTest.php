<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReader;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPolymorphicManyToManyComment;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPolymorphicManyToManyPost;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPolymorphicManyToManyTag;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPolymorphicManyToManyVideo;

/**
 * Integration test for polymorphic many-to-many relations end-to-end functionality.
 */
class PolymorphicManyToManyIntegrationTest extends AbstractTestCase {
    public function testPolymorphicManyToManyRelationsEndToEnd()
    {
        // Create mock schema reader that returns empty database
        $schemaReader = $this->createMock(DatabaseSchemaReader::class);
        $schemaReader->method('getTables')->willReturn([]);
        $schemaReader->method('getTableColumns')->willReturn([]);
        $schemaReader->method('getTableIndexes')->willReturn([]);
        $schemaReader->method('getTableForeignKeys')->willReturn([]);

        $schemaComparator = new DatabaseSchemaComparator($schemaReader, new SchemaNaming());

        // Use the actual test entities we created
        $postEntity = new ReflectionEntity(TestPolymorphicManyToManyPost::class);
        $videoEntity = new ReflectionEntity(TestPolymorphicManyToManyVideo::class);
        $commentEntity = new ReflectionEntity(TestPolymorphicManyToManyComment::class);
        $tagEntity = new ReflectionEntity(TestPolymorphicManyToManyTag::class);

        $results = iterator_to_array($schemaComparator->compareAll([$postEntity, $videoEntity, $commentEntity, $tagEntity]));

        // Should generate 5 tables: post, video, comment, tag, taggables
        $this->assertCount(5, $results);

        // Find the taggables mapping table
        $mappingTable = null;
        foreach ($results as $result) {
            if ($result->name === 'taggables') {
                $mappingTable = $result;

                break;
            }
        }

        $this->assertNotNull($mappingTable, 'Taggables mapping table should be generated');
        $this->assertEquals('create', $mappingTable->operation);

        // Mapping table should have 4 columns: taggable_type, taggable_id, test_polymorphic_many_to_many_tag_id, id
        $this->assertCount(4, $mappingTable->columns);

        $columnNames = array_map(fn ($col) => $col->name, $mappingTable->columns);
        $this->assertContains('taggable_type', $columnNames);
        $this->assertContains('taggable_id', $columnNames);
        $this->assertContains('test_polymorphic_many_to_many_tag_id', $columnNames);
        $this->assertContains('id', $columnNames);

        // Should have composite primary key including type, id, and target columns
        $this->assertCount(3, $mappingTable->primaryColumns);
        $this->assertEquals(['taggable_type', 'taggable_id', 'test_polymorphic_many_to_many_tag_id'], $mappingTable->primaryColumns);

        // Should have foreign key to the target table only
        $this->assertCount(1, $mappingTable->foreignKeys);

        $fkColumnNames = [];
        foreach ($mappingTable->foreignKeys as $fk) {
            $fkColumnNames[] = $fk->column;
        }
        $this->assertContains('test_polymorphic_many_to_many_tag_id', $fkColumnNames);
    }

    public function testPolymorphicManyToManyWithExistingSchema()
    {
        // Test updating existing schema to add polymorphic many-to-many columns
        $existingColumns = [
            'id' => (object) ['name' => 'id', 'type' => 'int', 'isNullable' => false, 'defaultValue' => null, 'length' => null],
        ];

        $schemaReader = $this->createMock(DatabaseSchemaReader::class);
        $schemaReader->method('getTables')->willReturn(['taggables']);
        $schemaReader->method('getTableColumns')->willReturnCallback(function ($table) use ($existingColumns) {
            return $table === 'taggables' ? array_values($existingColumns) : [];
        });
        $schemaReader->method('getTableIndexes')->willReturn([]);
        $schemaReader->method('getTableForeignKeys')->willReturn([]);

        $schemaComparator = new DatabaseSchemaComparator($schemaReader, new SchemaNaming());

        $postEntity = new ReflectionEntity(TestPolymorphicManyToManyPost::class);
        $tagEntity = new ReflectionEntity(TestPolymorphicManyToManyTag::class);
        $results = iterator_to_array($schemaComparator->compareAll([$postEntity, $tagEntity]));

        // Should have 3 results: update taggables table, create post table, create tag table
        $this->assertCount(3, $results);

        $taggablesResult = null;
        foreach ($results as $result) {
            if ($result->name === 'taggables') {
                $taggablesResult = $result;

                break;
            }
        }

        $this->assertNotNull($taggablesResult);
        $this->assertEquals('update', $taggablesResult->operation);

        // Should add 3 new columns: taggable_type, taggable_id, test_polymorphic_many_to_many_tag_id
        $this->assertCount(3, $taggablesResult->columns);
        $this->assertEquals('taggable_type', $taggablesResult->columns[0]->name);
        $this->assertEquals('create', $taggablesResult->columns[0]->operation);
        $this->assertEquals('taggable_id', $taggablesResult->columns[1]->name);
        $this->assertEquals('create', $taggablesResult->columns[1]->operation);
        $this->assertEquals('test_polymorphic_many_to_many_tag_id', $taggablesResult->columns[2]->name);
        $this->assertEquals('create', $taggablesResult->columns[2]->operation);
    }
}
