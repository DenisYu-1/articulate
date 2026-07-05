<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Attributes\Relations\MorphToMany;
use Articulate\Attributes\Relations\MorphTypeRegistry;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Entity(tableName: 'mtm_alias_posts')]
class MorphAliasPost {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $title;

    #[MorphToMany(targetEntity: MorphAliasTag::class, name: 'taggable', targetIdColumn: 'tag_id')]
    public iterable $tags;
}

#[Entity(tableName: 'mtm_alias_tags')]
class MorphAliasTag {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[MorphedByMany(targetEntity: MorphAliasPost::class, name: 'taggable', targetIdColumn: 'tag_id')]
    public array $posts;
}

class MorphToManyAliasTest extends DatabaseTestCase {
    private EntityManager $em;

    protected function tearDown(): void
    {
        MorphTypeRegistry::clear();
        parent::tearDown();
    }

    private function prepareTables(): void
    {
        MorphTypeRegistry::clear();
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->cleanUpTables(['taggables', 'mtm_alias_posts', 'mtm_alias_tags']);
        $this->currentConnection->executeQuery(
            'CREATE TABLE mtm_alias_posts (id INT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE mtm_alias_tags (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE taggables (
                tag_id INT NOT NULL,
                taggable_type VARCHAR(255) NOT NULL,
                taggable_id INT NOT NULL,
                PRIMARY KEY (taggable_type, taggable_id, tag_id)
            )'
        );
        $this->em = new EntityManager($this->currentConnection);
    }

    #[Group('database')]
    public function testMorphToManyLoadsRowsWithRegisteredAlias(): void
    {
        $this->prepareTables();
        MorphTypeRegistry::register(MorphAliasPost::class, 'post');

        [$post, $tag] = $this->insertPostTagAndPivot('post');

        $tags = $this->em->loadRelation($post, 'tags');

        $this->assertCount(1, $tags);
        $this->assertInstanceOf(MorphAliasTag::class, $tags[0]);
        $this->assertSame($tag->id, $tags[0]->id);
    }

    #[Group('database')]
    public function testMorphedByManyLoadsRowsWithRegisteredAlias(): void
    {
        $this->prepareTables();
        MorphTypeRegistry::register(MorphAliasPost::class, 'post');

        [$post, $tag] = $this->insertPostTagAndPivot('post');

        $posts = $this->em->loadRelation($tag, 'posts');

        $this->assertCount(1, $posts);
        $this->assertInstanceOf(MorphAliasPost::class, $posts[0]);
        $this->assertSame($post->id, $posts[0]->id);
    }

    #[Group('database')]
    public function testMorphToManyLoadsRowsWithClassNameFallback(): void
    {
        $this->prepareTables();

        [$post, $tag] = $this->insertPostTagAndPivot(MorphAliasPost::class);

        $tags = $this->em->loadRelation($post, 'tags');
        $posts = $this->em->loadRelation($tag, 'posts');

        $this->assertCount(1, $tags);
        $this->assertSame($tag->id, $tags[0]->id);
        $this->assertCount(1, $posts);
        $this->assertSame($post->id, $posts[0]->id);
    }

    #[Group('database')]
    public function testFlushPersistsMorphToManyCollectionWithAlias(): void
    {
        $this->prepareTables();
        MorphTypeRegistry::register(MorphAliasPost::class, 'post');

        $tag = new MorphAliasTag();
        $tag->name = 'ORM';

        $post = new MorphAliasPost();
        $post->title = 'Polymorphic relations';
        $post->tags = new Collection([$tag]);

        $this->em->persist($tag);
        $this->em->persist($post);
        $this->em->flush();

        $rows = $this->currentConnection->executeQuery('SELECT * FROM taggables')->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertSame($tag->id, (int) $rows[0]['tag_id']);
        $this->assertSame('post', $rows[0]['taggable_type']);
        $this->assertSame($post->id, (int) $rows[0]['taggable_id']);
    }

    /** @return array{MorphAliasPost, MorphAliasTag} */
    private function insertPostTagAndPivot(string $type): array
    {
        $this->currentConnection->executeQuery('INSERT INTO mtm_alias_posts (title) VALUES (?)', ['Post']);
        $postId = (int) $this->currentConnection->executeQuery('SELECT LAST_INSERT_ID() as id')->fetch()['id'];
        $this->currentConnection->executeQuery('INSERT INTO mtm_alias_tags (name) VALUES (?)', ['Tag']);
        $tagId = (int) $this->currentConnection->executeQuery('SELECT LAST_INSERT_ID() as id')->fetch()['id'];
        $this->currentConnection->executeQuery(
            'INSERT INTO taggables (tag_id, taggable_type, taggable_id) VALUES (?, ?, ?)',
            [$tagId, $type, $postId]
        );

        $post = new MorphAliasPost();
        $post->id = $postId;
        $post->title = 'Post';

        $tag = new MorphAliasTag();
        $tag->id = $tagId;
        $tag->name = 'Tag';

        return [$post, $tag];
    }
}
