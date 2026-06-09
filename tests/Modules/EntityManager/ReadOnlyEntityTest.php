<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Exceptions\ReadOnlyEntityException;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Tests\AbstractTestCase;
use Exception;

#[Entity(readOnly: true)]
class ReadOnlyEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;
}

#[Entity(tableName: 'read_only_hidden', readOnly: true)]
class ReadOnlyEntityWithHiddenRequired {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;
    // 'secret' column exists in DB as NOT NULL with no default — not mapped,
    // but safe because read-only entities are never inserted via ORM
}

class ReadOnlyEntityTest extends AbstractTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $connection->executeQuery('DROP TABLE IF EXISTS read_only_entity');
            $connection->executeQuery('DROP TABLE IF EXISTS read_only_hidden');

            $sql = match ($databaseName) {
                'mysql' => 'CREATE TABLE read_only_entity (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
                'pgsql' => 'CREATE TABLE read_only_entity (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
                default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
            };
            $connection->executeQuery($sql);

            $sqlHidden = match ($databaseName) {
                'mysql' => 'CREATE TABLE read_only_hidden (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, secret VARCHAR(255) NOT NULL)',
                'pgsql' => 'CREATE TABLE read_only_hidden (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, secret VARCHAR(255) NOT NULL)',
                default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
            };
            $connection->executeQuery($sqlHidden);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS read_only_entity');
        $connection->executeQuery('DROP TABLE IF EXISTS read_only_hidden');
    }

    public function testPersistReadOnlyEntityThrows(): void
    {
        $uow = new UnitOfWork();
        $entity = new ReadOnlyEntity();
        $entity->name = 'Alice';

        $this->expectException(ReadOnlyEntityException::class);
        $uow->persist($entity);
    }

    public function testRemoveReadOnlyEntityThrows(): void
    {
        $uow = new UnitOfWork();
        $entity = new ReadOnlyEntity();

        $this->expectException(ReadOnlyEntityException::class);
        $uow->remove($entity);
    }

    public function testFindReadOnlyEntityWorks(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $connection->executeQuery("INSERT INTO read_only_entity (name) VALUES ('Bob')");
            $id = (int) $connection->lastInsertId();

            $em = new EntityManager($connection);
            $entity = $em->find(ReadOnlyEntity::class, $id);

            $this->assertNotNull($entity);
            $this->assertSame('Bob', $entity->name);
        });
    }

    public function testQueryBuilderOnReadOnlyEntityWorks(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $connection->executeQuery("INSERT INTO read_only_entity (name) VALUES ('Carol')");

            $em = new EntityManager($connection);
            $results = $em->createQueryBuilder(ReadOnlyEntity::class)
                ->from('read_only_entity')
                ->where('name', 'Carol')
                ->getResult();

            $this->assertCount(1, $results);
            $this->assertSame('Carol', $results[0]->name);
        });
    }

    public function testFindReadOnlyEntityWithUnmappedRequiredColumnSucceeds(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $connection->executeQuery("INSERT INTO read_only_hidden (name, secret) VALUES ('Alice', 'xyz')");
            $id = (int) $connection->lastInsertId();

            $em = new EntityManager($connection);
            $entity = $em->find(ReadOnlyEntityWithHiddenRequired::class, $id);

            $this->assertNotNull($entity);
            $this->assertSame('Alice', $entity->name);
        });
    }

    private function runTestForAllDatabases(callable $test): void
    {
        $ran = false;
        foreach (['mysql', 'pgsql'] as $db) {
            if ($this->isDatabaseAvailable($db)) {
                $test($this->getConnection($db), $db);
                $ran = true;
            }
        }
        if (!$ran) {
            $this->markTestSkipped('No database available.');
        }
    }
}
