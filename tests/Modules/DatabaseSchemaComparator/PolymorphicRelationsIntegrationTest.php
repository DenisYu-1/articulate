<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Entity as EntityAttr;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestCommentEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMorphToEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPostEntity;
use Exception;

#[EntityAttr(tableName: 'test_polymorphic_entity')]
class TestPolymorphicEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property(maxLength: 255)]
    public string $title;

    #[MorphTo]
    public $pollable;
}

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

    /**
     * Test that polymorphic relationship loading works correctly.
     */
    /**
     * Test that polymorphic relationship loading works correctly.
     */
    public function testPolymorphicRelationshipLoading(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            // Create entity manager
            $entityManager = new EntityManager($connection);

            // Create test entities
            $post = new TestPostEntity();
            $post->title = 'Test Post';
            $post->content = 'Test content';

            // Persist entities to get IDs
            $entityManager->persist($post);
            $entityManager->flush();

            // Create morph entity that points to the post
            $pollForPost = new TestPolymorphicEntity();
            $pollForPost->title = 'Poll for Post';
            $pollForPost->pollable = $post; // Assign the relationship directly

            // Persist the morph entity
            $entityManager->persist($pollForPost);
            $entityManager->flush();

            // Check that the entity got an ID
            $this->assertNotNull($pollForPost->id, 'Poll entity should have an ID after flush');

            // Test loading MorphTo relationship
            $loadedPollForPost = $entityManager->find(TestPolymorphicEntity::class, $pollForPost->id);
            $this->assertNotNull($loadedPollForPost, 'Should be able to find the persisted entity');

            // Test that the relationship loading works
            $this->assertNotNull($loadedPollForPost->pollable, 'The pollable relationship should be loaded');
            $this->assertInstanceOf(TestPostEntity::class, $loadedPollForPost->pollable, 'The pollable should be an instance of TestPostEntity');
            $this->assertEquals($post->id, $loadedPollForPost->pollable->id, 'The loaded post should have the correct ID');

            // Clean up
            $entityManager->clear();
        });
    }

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            // Create test tables for polymorphic relationships
            $this->createTestTables($connection, $databaseName);

            return true;
        } catch (Exception $e) {
            // If table creation fails, try to drop and recreate
            try {
                $this->dropTestTables($connection);
                $this->createTestTables($connection, $databaseName);

                return true;
            } catch (Exception $dropException) {
                // If we still can't create the tables, skip this database
                return false;
            }
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $this->dropTestTables($connection);
    }

    private function createTestTables(Connection $connection, string $databaseName): void
    {
        if ($databaseName === 'mysql') {
            // Create test_post_entity table
            $connection->executeQuery('
                CREATE TABLE test_post_entity (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL
                )
            ');

            // Create test_comment_entity table
            $connection->executeQuery('
                CREATE TABLE test_comment_entity (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    content VARCHAR(500) NOT NULL,
                    post_id INT NOT NULL
                )
            ');

            // Create test_morph_to_entity table
            $connection->executeQuery('
                CREATE TABLE test_morph_to_entity (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    pollable_type VARCHAR(255) NOT NULL,
                    pollable_id INT NOT NULL
                )
            ');

            // Create test_polymorphic_entity table for polymorphic loading test
            $connection->executeQuery('
                CREATE TABLE test_polymorphic_entity (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    pollable_type VARCHAR(255) NOT NULL,
                    pollable_id INT NOT NULL
                )
            ');
        } else { // PostgreSQL
            // Create test_post_entity table
            $connection->executeQuery('
                CREATE TABLE test_post_entity (
                    id SERIAL PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL
                )
            ');

            // Create test_comment_entity table
            $connection->executeQuery('
                CREATE TABLE test_comment_entity (
                    id SERIAL PRIMARY KEY,
                    content VARCHAR(500) NOT NULL,
                    post_id INT NOT NULL
                )
            ');

            // Create test_morph_to_entity table
            $connection->executeQuery('
                CREATE TABLE test_morph_to_entity (
                    id SERIAL PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    pollable_type VARCHAR(255) NOT NULL,
                    pollable_id INT NOT NULL
                )
            ');

            // Create test_polymorphic_entity table for polymorphic loading test
            $connection->executeQuery('
                CREATE TABLE test_polymorphic_entity (
                    id SERIAL PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    pollable_type VARCHAR(255) NOT NULL,
                    pollable_id INT NOT NULL
                )
            ');
        }
    }

    private function dropTestTables(Connection $connection): void
    {
        $tables = ['test_morph_to_entity', 'test_polymorphic_entity', 'test_comment_entity', 'test_post_entity'];
        foreach ($tables as $table) {
            $connection->executeQuery("DROP TABLE IF EXISTS {$table}");
        }
    }

    private function runTestForAllDatabases(callable $testFunction): void
    {
        $databases = ['mysql', 'pgsql'];

        foreach ($databases as $databaseName) {
            // Skip databases that are not available
            if (!$this->isDatabaseAvailable($databaseName)) {
                continue;
            }

            $connection = $this->getConnection($databaseName);

            try {
                $testFunction($connection, $databaseName);
            } catch (Exception $e) {
                throw $e;
            }
        }
    }
}
