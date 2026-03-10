<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity(tableName: 'muow_users')]
class MuowUser {
    #[PrimaryKey]
    #[AutoIncrement]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public string $email;
}

#[Entity(tableName: 'muow_posts')]
class MuowPost {
    #[PrimaryKey]
    #[AutoIncrement]
    public ?int $id = null;

    #[Property]
    public string $title;

    #[ManyToOne(targetEntity: MuowUser::class)]
    public MuowUser $author;
}

class MultipleUnitOfWorkTest extends AbstractTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        $connection->executeQuery('DROP TABLE IF EXISTS muow_posts');
        $connection->executeQuery('DROP TABLE IF EXISTS muow_users');

        if ($databaseName === 'mysql') {
            $connection->executeQuery('CREATE TABLE muow_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
            $connection->executeQuery('CREATE TABLE muow_posts (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, author_id INT NOT NULL)');
        } else {
            $connection->executeQuery('CREATE TABLE muow_users (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
            $connection->executeQuery('CREATE TABLE muow_posts (id SERIAL PRIMARY KEY, title VARCHAR(255) NOT NULL, author_id INT NOT NULL)');
        }

        return true;
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS muow_posts');
        $connection->executeQuery('DROP TABLE IF EXISTS muow_users');
    }

    public static function databaseProvider(): array
    {
        return [['mysql'], ['pgsql']];
    }

    #[DataProvider('databaseProvider')]
    public function testInsertedEntityIsNotReUpdatedOnSubsequentFlush(string $databaseName): void
    {
        if (!$this->isDatabaseAvailable($databaseName)) {
            $this->markTestSkipped("{$databaseName} not available");
        }

        $connection = $this->getConnection($databaseName);
        $entityManager = new EntityManager($connection);

        $user = new MuowUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $entityManager->persist($user);
        $entityManager->flush();

        $this->assertNotNull($user->id);

        $userCountBefore = $this->countTableRows($connection, 'muow_users');

        $postUow = $entityManager->createUnitOfWork();
        $post = new MuowPost();
        $post->title = 'Hello World';
        $post->author = $user;

        $postUow->persist($post);
        $entityManager->flush();

        $userCountAfter = $this->countTableRows($connection, 'muow_users');

        $this->assertEquals(1, $userCountBefore);
        $this->assertEquals(1, $userCountAfter, 'User row count must not change — no spurious UPDATE');

        $retrievedUser = $entityManager->find(MuowUser::class, $user->id);
        $this->assertEquals('Alice', $retrievedUser->name);
        $this->assertEquals('alice@example.com', $retrievedUser->email);
    }

    #[DataProvider('databaseProvider')]
    public function testManyToOneForeignKeyIsPersistedOnInsert(string $databaseName): void
    {
        if (!$this->isDatabaseAvailable($databaseName)) {
            $this->markTestSkipped("{$databaseName} not available");
        }

        $connection = $this->getConnection($databaseName);
        $entityManager = new EntityManager($connection);

        $user = new MuowUser();
        $user->name = 'Bob';
        $user->email = 'bob@example.com';

        $entityManager->persist($user);
        $entityManager->flush();

        $this->assertNotNull($user->id);

        $post = new MuowPost();
        $post->title = 'My First Post';
        $post->author = $user;

        $entityManager->persist($post);
        $entityManager->flush();

        $this->assertNotNull($post->id, 'Post ID must be set after insert');

        $row = $connection->executeQuery('SELECT author_id FROM muow_posts WHERE id = ?', [$post->id])->fetch();

        $this->assertNotNull($row, 'Post must exist in database');
        $this->assertEquals($user->id, (int) $row['author_id'], 'author_id FK must match the user id');
    }

    #[DataProvider('databaseProvider')]
    public function testMultiplePostsViaSecondaryUnitOfWorkWithoutSpuriousUserUpdates(string $databaseName): void
    {
        if (!$this->isDatabaseAvailable($databaseName)) {
            $this->markTestSkipped("{$databaseName} not available");
        }

        $connection = $this->getConnection($databaseName);
        $entityManager = new EntityManager($connection);

        $user = new MuowUser();
        $user->name = 'Carol';
        $user->email = 'carol@example.com';

        $entityManager->persist($user);
        $entityManager->flush();

        $postUow = $entityManager->createUnitOfWork();

        for ($i = 1; $i <= 3; $i++) {
            $post = new MuowPost();
            $post->title = "Post #{$i}";
            $post->author = $user;

            $postUow->persist($post);
            $entityManager->flush();
            $postUow->clear();
        }

        $postCount = $this->countTableRows($connection, 'muow_posts');
        $this->assertEquals(3, $postCount, 'All three posts must be inserted');

        $userCount = $this->countTableRows($connection, 'muow_users');
        $this->assertEquals(1, $userCount, 'User row count must stay at one');

        $rows = $connection->executeQuery('SELECT author_id FROM muow_posts')->fetchAll();
        foreach ($rows as $row) {
            $this->assertEquals($user->id, (int) $row['author_id'], 'Every post must reference the correct user');
        }
    }

    private function countTableRows(Connection $connection, string $table): int
    {
        return (int) $connection->executeQuery("SELECT COUNT(*) AS cnt FROM {$table}")->fetch()['cnt'];
    }
}
