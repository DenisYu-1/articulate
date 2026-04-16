<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\HydratorInterface;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Group;

// ── Fixture entities ──────────────────────────────────────────────────────────

#[Entity(tableName: 'cnt_rl_authors')]
class CntAuthor {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[OneToMany(targetEntity: CntBook::class, ownedBy: 'author')]
    public ?Collection $books = null;
}

#[Entity(tableName: 'cnt_rl_books')]
class CntBook {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $title;

    #[ManyToOne(targetEntity: CntAuthor::class)]
    public ?CntAuthor $author = null;
}

#[Entity(tableName: 'cnt_rl_articles')]
class CntArticle {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $headline;

    #[ManyToMany(targetEntity: CntTag::class, referencedBy: 'articles')]
    public ?Collection $tags = null;
}

#[Entity(tableName: 'cnt_rl_tags')]
class CntTag {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $label;
}

// ── Test class ────────────────────────────────────────────────────────────────

class RelationshipLoaderCountTest extends DatabaseTestCase {
    private RelationshipLoader $loader;

    private EntityManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function buildLoader(): void
    {
        $hydrator = new class() implements HydratorInterface {
            public function hydrate(string $class, array $data, ?object $entity = null): mixed
            {
                $entity ??= new $class();
                $reflection = new \ReflectionClass($entity);
                foreach ($data as $col => $value) {
                    $prop = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $col))));
                    if ($reflection->hasProperty($prop)) {
                        $reflection->getProperty($prop)->setAccessible(true);
                        $reflection->getProperty($prop)->setValue($entity, $value);
                    }
                }

                return $entity;
            }

            public function extract(mixed $entity): array
            {
                $data = [];
                $r = new \ReflectionClass($entity);
                foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                    $data[$p->getName()] = $entity->{$p->getName()} ?? null;
                }

                return $data;
            }

            public function hydratePartial(object $entity, array $data): void
            {
                foreach ($data as $k => $v) {
                    if (property_exists($entity, $k)) {
                        $entity->$k = $v;
                    }
                }
            }
        };

        $this->entityManager = new EntityManager($this->currentConnection, hydrator: $hydrator);
        $this->loader = new RelationshipLoader($this->entityManager, $this->entityManager->getMetadataRegistry());
    }

    // ─── OneToMany COUNT ──────────────────────────────────────────────────────

    #[Group('database')]
    public function testCountOneToManyReturnsCorrectCount(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['cnt_rl_books', 'cnt_rl_authors']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_authors (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_books (id INT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(255) NOT NULL, cnt_rl_authors_id INT NOT NULL)'
        );

        $authorId = $this->insertRow('cnt_rl_authors', ['name' => 'Alice']);
        $this->insertRow('cnt_rl_books', ['title' => 'Book A', 'cnt_rl_authors_id' => $authorId]);
        $this->insertRow('cnt_rl_books', ['title' => 'Book B', 'cnt_rl_authors_id' => $authorId]);
        $this->insertRow('cnt_rl_books', ['title' => 'Book C', 'cnt_rl_authors_id' => $authorId]);

        $author = new CntAuthor();
        $author->id = $authorId;
        $author->name = 'Alice';

        $relation = (new EntityMetadata(CntAuthor::class))->getRelation('books');
        $this->assertNotNull($relation);
        $this->assertTrue($relation->isOneToMany());

        $count = $this->loader->count($author, $relation);

        $this->assertSame(3, $count);
    }

    #[Group('database')]
    public function testCountOneToManyReturnsZeroWhenNoRelated(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['cnt_rl_books', 'cnt_rl_authors']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_authors (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_books (id INT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(255) NOT NULL, cnt_rl_authors_id INT NOT NULL)'
        );

        $authorId = $this->insertRow('cnt_rl_authors', ['name' => 'Bob']);
        // no books inserted

        $author = new CntAuthor();
        $author->id = $authorId;
        $author->name = 'Bob';

        $relation = (new EntityMetadata(CntAuthor::class))->getRelation('books');
        $this->assertNotNull($relation);

        $count = $this->loader->count($author, $relation);

        $this->assertSame(0, $count);
    }

    #[Group('database')]
    public function testCountOneToManyIsolatedPerOwner(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['cnt_rl_books', 'cnt_rl_authors']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_authors (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_books (id INT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(255) NOT NULL, cnt_rl_authors_id INT NOT NULL)'
        );

        $author1Id = $this->insertRow('cnt_rl_authors', ['name' => 'Author1']);
        $author2Id = $this->insertRow('cnt_rl_authors', ['name' => 'Author2']);

        $this->insertRow('cnt_rl_books', ['title' => 'A1', 'cnt_rl_authors_id' => $author1Id]);
        $this->insertRow('cnt_rl_books', ['title' => 'A2', 'cnt_rl_authors_id' => $author1Id]);
        $this->insertRow('cnt_rl_books', ['title' => 'B1', 'cnt_rl_authors_id' => $author2Id]);

        $author1 = new CntAuthor();
        $author1->id = $author1Id;
        $author2 = new CntAuthor();
        $author2->id = $author2Id;

        $relation = (new EntityMetadata(CntAuthor::class))->getRelation('books');
        $this->assertNotNull($relation);

        $this->assertSame(2, $this->loader->count($author1, $relation));
        $this->assertSame(1, $this->loader->count($author2, $relation));
    }

    // ─── ManyToMany COUNT ─────────────────────────────────────────────────────

    #[Group('database')]
    public function testCountManyToManyReturnsCorrectCount(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();

        // Pivot table name: sorted(cnt_rl_articles, cnt_rl_tags) → cnt_rl_articles_cnt_rl_tags
        // Owner FK: cnt_rl_articles_id (owner declaring class table)
        // Related FK: cnt_rl_tags_id
        $this->cleanUpTables(['cnt_rl_articles_cnt_rl_tags', 'cnt_rl_articles', 'cnt_rl_tags']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_articles (id INT PRIMARY KEY AUTO_INCREMENT, headline VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_tags (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_articles_cnt_rl_tags (cnt_rl_articles_id INT NOT NULL, cnt_rl_tags_id INT NOT NULL)'
        );

        $articleId = $this->insertRow('cnt_rl_articles', ['headline' => 'News']);
        $tag1Id = $this->insertRow('cnt_rl_tags', ['label' => 'php']);
        $tag2Id = $this->insertRow('cnt_rl_tags', ['label' => 'orm']);
        $tag3Id = $this->insertRow('cnt_rl_tags', ['label' => 'lazy']);

        $this->currentConnection->executeQuery(
            'INSERT INTO cnt_rl_articles_cnt_rl_tags (cnt_rl_articles_id, cnt_rl_tags_id) VALUES (?, ?)',
            [$articleId, $tag1Id]
        );
        $this->currentConnection->executeQuery(
            'INSERT INTO cnt_rl_articles_cnt_rl_tags (cnt_rl_articles_id, cnt_rl_tags_id) VALUES (?, ?)',
            [$articleId, $tag2Id]
        );
        $this->currentConnection->executeQuery(
            'INSERT INTO cnt_rl_articles_cnt_rl_tags (cnt_rl_articles_id, cnt_rl_tags_id) VALUES (?, ?)',
            [$articleId, $tag3Id]
        );

        $article = new CntArticle();
        $article->id = $articleId;
        $article->headline = 'News';

        $relation = (new EntityMetadata(CntArticle::class))->getRelation('tags');
        $this->assertNotNull($relation);
        $this->assertTrue($relation->isManyToMany());

        $count = $this->loader->count($article, $relation);

        $this->assertSame(3, $count);
    }

    #[Group('database')]
    public function testCountManyToManyReturnsZeroWhenNoPivotRows(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['cnt_rl_articles_cnt_rl_tags', 'cnt_rl_articles', 'cnt_rl_tags']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_articles (id INT PRIMARY KEY AUTO_INCREMENT, headline VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_tags (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_articles_cnt_rl_tags (cnt_rl_articles_id INT NOT NULL, cnt_rl_tags_id INT NOT NULL)'
        );

        $articleId = $this->insertRow('cnt_rl_articles', ['headline' => 'Empty']);
        // no pivot rows

        $article = new CntArticle();
        $article->id = $articleId;
        $article->headline = 'Empty';

        $relation = (new EntityMetadata(CntArticle::class))->getRelation('tags');
        $this->assertNotNull($relation);

        $count = $this->loader->count($article, $relation);

        $this->assertSame(0, $count);
    }

    #[Group('database')]
    public function testCountManyToManyIsolatedPerOwner(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['cnt_rl_articles_cnt_rl_tags', 'cnt_rl_articles', 'cnt_rl_tags']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_articles (id INT PRIMARY KEY AUTO_INCREMENT, headline VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_tags (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE cnt_rl_articles_cnt_rl_tags (cnt_rl_articles_id INT NOT NULL, cnt_rl_tags_id INT NOT NULL)'
        );

        $article1Id = $this->insertRow('cnt_rl_articles', ['headline' => 'Article1']);
        $article2Id = $this->insertRow('cnt_rl_articles', ['headline' => 'Article2']);
        $tag1Id = $this->insertRow('cnt_rl_tags', ['label' => 'alpha']);
        $tag2Id = $this->insertRow('cnt_rl_tags', ['label' => 'beta']);
        $tag3Id = $this->insertRow('cnt_rl_tags', ['label' => 'gamma']);

        // article1 has 2 tags, article2 has 1
        $this->currentConnection->executeQuery(
            'INSERT INTO cnt_rl_articles_cnt_rl_tags VALUES (?, ?)',
            [$article1Id, $tag1Id]
        );
        $this->currentConnection->executeQuery(
            'INSERT INTO cnt_rl_articles_cnt_rl_tags VALUES (?, ?)',
            [$article1Id, $tag2Id]
        );
        $this->currentConnection->executeQuery(
            'INSERT INTO cnt_rl_articles_cnt_rl_tags VALUES (?, ?)',
            [$article2Id, $tag3Id]
        );

        $article1 = new CntArticle();
        $article1->id = $article1Id;
        $article2 = new CntArticle();
        $article2->id = $article2Id;

        $relation = (new EntityMetadata(CntArticle::class))->getRelation('tags');
        $this->assertNotNull($relation);

        $this->assertSame(2, $this->loader->count($article1, $relation));
        $this->assertSame(1, $this->loader->count($article2, $relation));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function insertRow(string $table, array $values): int
    {
        $cols = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->currentConnection->executeQuery(
            "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})",
            array_values($values)
        );

        $result = $this->currentConnection->executeQuery('SELECT LAST_INSERT_ID() as id');

        return (int) $result->fetch()['id'];
    }
}
