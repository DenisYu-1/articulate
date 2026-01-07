<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityMetadata;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Tests\DatabaseTestCase;

#[Entity(tableName: 'test_authors')]
class TestAuthor {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[OneToMany(targetEntity: TestBook::class, ownedBy: 'author')]
    public ?Collection $books;
}

#[Entity(tableName: 'test_books')]
class TestBook {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $title;

    #[Property(name: 'author_id')]
    public int $authorId;

    #[ManyToOne(targetEntity: TestAuthor::class)]
    public ?TestAuthor $author;
}

class RelationshipLoaderTest extends DatabaseTestCase {
    private RelationshipLoader $loader;

    private EntityManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        // Note: We don't initialize entityManager here as it depends on the database connection
        // which is set up in individual test methods
    }

    /**
     * Simple hydrator that doesn't load relationships automatically.
     */
    private function createSimpleHydrator(): HydratorInterface
    {
        return new class() implements HydratorInterface {
            public function hydrate(string $class, array $data, ?object $entity = null): mixed
            {
                $entity ??= new $class();
                $reflection = new \ReflectionClass($entity);

                foreach ($data as $columnName => $value) {
                    // Try to find the property name (could be snake_case to camelCase conversion)
                    $propertyName = $this->columnToProperty($columnName);

                    if ($reflection->hasProperty($propertyName)) {
                        $property = $reflection->getProperty($propertyName);
                        $property->setAccessible(true);
                        $property->setValue($entity, $value);
                    }
                }

                return $entity;
            }

            private function columnToProperty(string $columnName): string
            {
                // Convert snake_case to camelCase
                return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $columnName))));
            }

            public function extract(mixed $entity): array
            {
                $data = [];
                $reflection = new \ReflectionClass($entity);

                foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                    $name = $property->getName();
                    $data[$name] = $entity->$name ?? null;
                }

                return $data;
            }

            public function hydratePartial(object $entity, array $data): void
            {
                foreach ($data as $property => $value) {
                    if (property_exists($entity, $property)) {
                        $entity->$property = $value;
                    }
                }
            }
        };
    }

    /**
     * Test OneToMany relationship loading.
     *
     * @group database
     */
    public function testLoadOneToManyRelationship(): void
    {
        $databaseName = 'mysql';
        $this->skipIfDatabaseNotAvailable($databaseName);
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        $simpleHydrator = $this->createSimpleHydrator();
        $this->entityManager = new EntityManager($connection, hydrator: $simpleHydrator);
        $this->loader = new RelationshipLoader($this->entityManager, $this->entityManager->getMetadataRegistry());

        // Clean up any existing tables
        $this->cleanUpTables(['test_authors', 'test_books']);

        // Create tables with exact names from entity attributes
        $this->createAuthorAndBookTables();

        // Insert test data
        $authorId = $this->insertAuthor('Test Author');
        $book1Id = $this->insertBook('Book 1', $authorId);
        $book2Id = $this->insertBook('Book 2', $authorId);

        // Create author entity
        $author = new TestAuthor();
        $author->id = $authorId;
        $author->name = 'Test Author';

        // Load the relationship
        $metadata = new EntityMetadata(TestAuthor::class);
        $relation = $metadata->getRelation('books');
        $this->assertNotNull($relation);
        $this->assertTrue($relation->isOneToMany());

        $books = $this->loader->load($author, $relation);

        // Verify the relationship was loaded correctly
        $this->assertIsArray($books);
        $this->assertCount(2, $books);

        // Check first book
        $this->assertInstanceOf(TestBook::class, $books[0]);
        $this->assertEquals($book1Id, $books[0]->id);
        $this->assertEquals('Book 1', $books[0]->title);
        $this->assertEquals($authorId, $books[0]->authorId);

        // Check second book
        $this->assertInstanceOf(TestBook::class, $books[1]);
        $this->assertEquals($book2Id, $books[1]->id);
        $this->assertEquals('Book 2', $books[1]->title);
        $this->assertEquals($authorId, $books[1]->authorId);
    }

    /**
     * Test ManyToOne relationship loading.
     *
     * @group database
     */
    public function testLoadManyToOneRelationship(): void
    {
        $databaseName = 'mysql';
        $this->skipIfDatabaseNotAvailable($databaseName);
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        $simpleHydrator = $this->createSimpleHydrator();
        $this->entityManager = new EntityManager($connection, hydrator: $simpleHydrator);
        $this->loader = new RelationshipLoader($this->entityManager, $this->entityManager->getMetadataRegistry());

        // Clean up any existing tables
        $this->cleanUpTables(['test_authors', 'test_books']);

        // Create tables with exact names from entity attributes
        $this->createAuthorAndBookTables();

        // Insert test data
        $authorId = $this->insertAuthor('Test Author');
        $bookId = $this->insertBook('Test Book', $authorId);

        // Create book entity
        $book = new TestBook();
        $book->id = $bookId;
        $book->title = 'Test Book';
        $book->authorId = $authorId;

        // Load the relationship
        $metadata = new EntityMetadata(TestBook::class);
        $relation = $metadata->getRelation('author');
        $this->assertNotNull($relation);
        $this->assertTrue($relation->isManyToOne());

        $author = $this->loader->load($book, $relation);

        // Verify the relationship was loaded correctly
        $this->assertInstanceOf(TestAuthor::class, $author);
        $this->assertEquals($authorId, $author->id);
        $this->assertEquals('Test Author', $author->name);
    }

    /**
     * Test relationship metadata configuration.
     */
    public function testRelationshipMetadata(): void
    {
        $authorMetadata = new EntityMetadata(TestAuthor::class);
        $bookMetadata = new EntityMetadata(TestBook::class);

        // Test OneToMany relationship metadata
        $booksRelation = $authorMetadata->getRelation('books');
        $this->assertNotNull($booksRelation);
        $this->assertTrue($booksRelation->isOneToMany());
        $this->assertEquals(TestBook::class, $booksRelation->getTargetEntity());

        // Test ManyToOne relationship metadata
        $authorRelation = $bookMetadata->getRelation('author');
        $this->assertNotNull($authorRelation);
        $this->assertTrue($authorRelation->isManyToOne());
        $this->assertEquals(TestAuthor::class, $authorRelation->getTargetEntity());
    }

    /**
     * Helper method to create author and book tables.
     */
    private function createAuthorAndBookTables(): void
    {
        // Create tables with exact names from entity attributes
        $createAuthorSql = 'CREATE TABLE test_authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)';
        $createBookSql = 'CREATE TABLE test_books (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, author_id INTEGER NOT NULL)';

        $this->currentConnection->executeQuery($createAuthorSql);
        $this->currentConnection->executeQuery($createBookSql);
    }

    /**
     * Helper method to insert an author and return the ID.
     */
    private function insertAuthor(string $name): int
    {
        $this->currentConnection->executeQuery('INSERT INTO test_authors (name) VALUES (?)', [$name]);
        $result = $this->currentConnection->executeQuery('SELECT last_insert_rowid() as id');

        return (int) $result->fetch()['id'];
    }

    /**
     * Helper method to insert a book and return the ID.
     */
    private function insertBook(string $title, int $authorId): int
    {
        $this->currentConnection->executeQuery('INSERT INTO test_books (title, author_id) VALUES (?, ?)', [$title, $authorId]);
        $result = $this->currentConnection->executeQuery('SELECT last_insert_rowid() as id');

        return (int) $result->fetch()['id'];
    }
}
