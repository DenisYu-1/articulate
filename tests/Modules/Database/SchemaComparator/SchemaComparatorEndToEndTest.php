<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity(tableName: 'test_users')]
class TestUserEntity {
    #[PrimaryKey(generator: PrimaryKey::GENERATOR_AUTO_INCREMENT, type: 'int')]
    public int $id;

    #[Property(type: 'string', maxLength: 255)]
    public string $name;

    #[Property(type: 'string', maxLength: 255)]
    public string $email;

    #[OneToMany(targetEntity: TestPostEntity::class, ownedBy: 'author')]
    public $posts;

    #[ManyToMany(targetEntity: TestRoleEntity::class, referencedBy: 'users')]
    public $roles;
}

#[Entity(tableName: 'test_posts')]
class TestPostEntity {
    #[PrimaryKey(generator: PrimaryKey::GENERATOR_AUTO_INCREMENT, type: 'int')]
    public int $id;

    #[Property(type: 'string', maxLength: 255)]
    public string $title;

    #[Property(type: 'text', maxLength: null)]
    public string $content;

    #[ManyToOne(targetEntity: TestUserEntity::class, referencedBy: 'posts')]
    public TestUserEntity $author;

    #[ManyToMany(targetEntity: TestTagEntity::class, referencedBy: 'posts')]
    public $tags;
}

#[Entity(tableName: 'test_roles')]
class TestRoleEntity {
    #[PrimaryKey(generator: PrimaryKey::GENERATOR_AUTO_INCREMENT, type: 'int')]
    public int $id;

    #[Property(type: 'string', maxLength: 100)]
    public string $name;

    #[ManyToMany(targetEntity: TestUserEntity::class, ownedBy: 'roles')]
    public $users;
}

#[Entity(tableName: 'test_tags')]
class TestTagEntity {
    #[PrimaryKey(generator: PrimaryKey::GENERATOR_AUTO_INCREMENT, type: 'int')]
    public int $id;

    #[Property(type: 'string', maxLength: 100)]
    public string $name;

    #[ManyToMany(targetEntity: TestPostEntity::class, ownedBy: 'tags')]
    public $posts;
}

class SchemaComparatorEndToEndTest extends DatabaseTestCase {
    private Connection $connection;

    private DatabaseSchemaReaderInterface $schemaReader;

    private DatabaseSchemaComparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();

        // Use MySQL for this test
        $this->connection = $this->getConnection('mysql');
        $this->setCurrentDatabase($this->connection, 'mysql');

        // Clean up any existing test tables
        $this->cleanUpTables(['test_users', 'test_posts', 'test_roles', 'test_tags', 'user_roles', 'post_tags']);

        $this->schemaReader = $this->createSchemaReader();
        $this->comparator = new DatabaseSchemaComparator($this->schemaReader, new SchemaNaming());
    }

    /**
     * @param string $databaseName
     * @return void
     */
    #[DataProvider('databaseProvider')]
    public function testEndToEndEntityCreation(string $databaseName): void
    {
        $this->connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($this->connection, $databaseName);

        // Clean up any existing test tables
        $this->cleanUpTables(['test_users', 'test_posts', 'test_roles', 'test_tags', 'user_roles', 'post_tags']);

        $this->schemaReader = $this->createSchemaReader();
        $this->comparator = new DatabaseSchemaComparator($this->schemaReader, new SchemaNaming());

        $entities = [
            new ReflectionEntity(TestUserEntity::class),
            new ReflectionEntity(TestPostEntity::class),
            new ReflectionEntity(TestRoleEntity::class),
            new ReflectionEntity(TestTagEntity::class),
        ];

        // Compare entities with empty database
        $comparisonResults = iterator_to_array($this->comparator->compareAll($entities));

        // Should have results for all entities and their relations
        $this->assertNotEmpty($comparisonResults);

        // Check that we have table creation results
        $tableNames = array_map(fn ($result) => $result->name, $comparisonResults);
        $this->assertContains('test_users', $tableNames);
        $this->assertContains('test_posts', $tableNames);
        $this->assertContains('test_roles', $tableNames);
        $this->assertContains('test_tags', $tableNames);

        // Check that we have many-to-many table creation results
        $this->assertContains('test_roles_test_users', $tableNames);
        $this->assertContains('test_posts_test_tags', $tableNames);

        // Verify table creation operations
        foreach ($comparisonResults as $result) {
            if (in_array($result->name, ['test_users', 'test_posts', 'test_roles', 'test_tags', 'user_roles', 'post_tags'])) {
                $this->assertEquals('create', $result->operation, "Table {$result->name} should be created");
                $this->assertNotEmpty($result->columns, "Table {$result->name} should have columns");
            }
        }

        // Test SQL generation for one of the tables
        $userTableResult = array_find($comparisonResults, fn ($result) => $result->name === 'test_users');
        $this->assertNotNull($userTableResult);

        // Generate SQL (this would normally go to a migration generator)
        $this->assertEquals('create', $userTableResult->operation);
        $this->assertGreaterThan(0, count($userTableResult->columns));

        // Check specific columns exist
        $columnNames = array_map(fn ($column) => $column->name, $userTableResult->columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);
    }

    /**
     * @param string $databaseName
     * @return void
     */
    #[DataProvider('databaseProvider')]
    public function testEndToEndTableDeletion(string $databaseName): void
    {
        $this->connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($this->connection, $databaseName);

        $this->schemaReader = $this->createSchemaReader();
        $this->comparator = new DatabaseSchemaComparator($this->schemaReader, new SchemaNaming());

        // Simulate database has extra tables that should be deleted
        $this->schemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $this->schemaReader->method('getTables')->willReturn(['old_table', 'another_old_table']);
        $this->schemaReader->method('getTableColumns')->willReturn([]);
        $this->schemaReader->method('getTableIndexes')->willReturn([]);
        $this->schemaReader->method('getTableForeignKeys')->willReturn([]);

        $this->comparator = new DatabaseSchemaComparator($this->schemaReader, new SchemaNaming());

        // Compare with empty entities (no entities defined)
        $deleteResults = iterator_to_array($this->comparator->compareAll([]));

        // Should detect tables to delete
        $this->assertCount(2, $deleteResults);

        foreach ($deleteResults as $result) {
            $this->assertEquals('delete', $result->operation);
            $this->assertContains($result->name, ['old_table', 'another_old_table']);
        }
    }

    private function createSchemaReader(): DatabaseSchemaReaderInterface
    {
        // Create a mock schema reader that behaves like an empty database
        $mock = $this->createMock(DatabaseSchemaReaderInterface::class);
        $mock->method('getTables')->willReturn([]);
        $mock->method('getTableColumns')->willReturn([]);
        $mock->method('getTableIndexes')->willReturn([]);
        $mock->method('getTableForeignKeys')->willReturn([]);

        return $mock;
    }
}
