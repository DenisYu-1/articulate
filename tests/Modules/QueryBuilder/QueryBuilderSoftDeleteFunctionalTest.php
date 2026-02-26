<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\SoftDeleteable;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\Filter\SoftDeleteFilter;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity]
#[SoftDeleteable(fieldName: 'deletedAt', columnName: 'deleted_at')]
class SoftDeleteableUser {
    #[PrimaryKey]
    #[AutoIncrement]
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public ?\DateTime $deletedAt = null;
}

class QueryBuilderSoftDeleteFunctionalTest extends DatabaseTestCase {
    private Connection $connection;

    private EntityManager $entityManager;

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $this->createSoftDeleteableUserTable($connection, $databaseName);

            return true;
        } catch (\Exception $e) {
            try {
                $connection->executeQuery('DROP TABLE IF EXISTS soft_deleteable_user');
                $this->createSoftDeleteableUserTable($connection, $databaseName);

                return true;
            } catch (\Exception $dropException) {
                return false;
            }
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS soft_deleteable_user');
    }

    private function createSoftDeleteableUserTable(Connection $connection, string $databaseName): void
    {
        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE soft_deleteable_user (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                deleted_at DATETIME NULL
            )',
            'pgsql' => 'CREATE TABLE soft_deleteable_user (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                deleted_at TIMESTAMP NULL
            )',
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}")
        };

        $connection->executeQuery($sql);
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteFilterExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Active User 1', null, 'Active User 2', null, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->select('id', 'name')
            ->from('soft_deleteable_user');

        $results = $qb->getResult();

        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        $this->assertContains('Active User 1', $names);
        $this->assertContains('Active User 2', $names);
        $this->assertNotContains('Deleted User', $names);
    }

    #[DataProvider('databaseProvider')]
    public function testWithDeletedIncludesAllEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Active User 1', null, 'Active User 2', null, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->select('id', 'name')
            ->from('soft_deleteable_user')
            ->withoutFilter('soft_delete');

        $results = $qb->getResult();

        $this->assertCount(3, $results);
        $names = array_column($results, 'name');
        $this->assertContains('Active User 1', $names);
        $this->assertContains('Active User 2', $names);
        $this->assertContains('Deleted User', $names);
    }

    #[DataProvider('databaseProvider')]
    public function testEntityManagerFindExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (id, name, deleted_at) VALUES (?, ?, ?), (?, ?, ?)',
            [1, 'Active User', null, 2, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $activeUser = $this->entityManager->find(SoftDeleteableUser::class, 1);
        $deletedUser = $this->entityManager->find(SoftDeleteableUser::class, 2);

        $this->assertNotNull($activeUser);
        $this->assertNull($deletedUser);
    }

    #[DataProvider('databaseProvider')]
    public function testEntityManagerFindAllExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Active User 1', null, 'Active User 2', null, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $allUsers = $this->entityManager->findAll(SoftDeleteableUser::class);

        $this->assertCount(2, $allUsers);
        $names = array_map(fn ($user) => $user->name, $allUsers);
        $this->assertContains('Active User 1', $names);
        $this->assertContains('Active User 2', $names);
        $this->assertNotContains('Deleted User', $names);
    }

    #[DataProvider('databaseProvider')]
    public function testEntityManagerFindAllWithDeletedWhenDisabled(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->entityManager->getFilters()->disable('soft_delete');

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Active User 1', null, 'Active User 2', null, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $allUsers = $this->entityManager->findAll(SoftDeleteableUser::class);

        $this->assertCount(3, $allUsers);
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteFilterWithWhereConditions(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['John', null, 'Jane', null, 'John', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->select('id', 'name')
            ->from('soft_deleteable_user')
            ->where('name = ?', 'John');

        $results = $qb->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('John', $results[0]['name']);
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteFilterInSubQuery(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (id, name, deleted_at) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)',
            [1, 'User 1', null, 2, 'User 2', null, 3, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $subQuery = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->select('id')
            ->from('soft_deleteable_user');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('*')
            ->from('soft_deleteable_user')
            ->whereIn('id', $subQuery);

        $results = $qb->getResult();

        $this->assertCount(2, $results);
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteFilterWithCount(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['User 1', null, 'User 2', null, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->count('*')
            ->from('soft_deleteable_user');

        $result = $qb->getSingleResult(null);

        // Debug: see what keys are available
        if ($result && is_array($result)) {
            $keys = array_keys($result);
            $firstKey = $keys[0] ?? null;
            $this->assertEquals(2, $result[$firstKey] ?? null);
        } else {
            $this->assertEquals(2, $result['COUNT(*)'] ?? $result['count(*)'] ?? null);
        }
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteFilterWithJoin(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $this->connection->executeQuery('DROP TABLE IF EXISTS posts');
        $this->connection->executeQuery('CREATE TABLE IF NOT EXISTS posts (
            id INT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255)
        )');

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (id, name, deleted_at) VALUES (?, ?, ?), (?, ?, ?)',
            [1, 'Active User', null, 2, 'Deleted User', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $this->connection->executeQuery(
            'INSERT INTO posts (id, user_id, title) VALUES (?, ?, ?), (?, ?, ?)',
            [1, 1, 'Post 1', 2, 2, 'Post 2']
        );

        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->select('u.id', 'u.name', 'p.title')
            ->from('soft_deleteable_user', 'u')
            ->join('posts p', 'p.user_id = u.id');

        $results = $qb->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Active User', $results[0]['name']);

        $this->connection->executeQuery('DROP TABLE IF EXISTS posts');
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteFilterWithGroupBy(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_user (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?), (?, ?)',
            ['John', null, 'John', null, 'Jane', null, 'John', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableUser::class)
            ->select('name')
            ->count('*', 'count')
            ->from('soft_deleteable_user')
            ->groupBy('name');

        $results = $qb->getResult();

        $this->assertCount(2, $results);
        $countsByName = [];
        foreach ($results as $result) {
            $countsByName[$result['name']] = $result['count'];
        }
        $this->assertEquals(2, $countsByName['John']);
        $this->assertEquals(1, $countsByName['Jane']);
    }
}
