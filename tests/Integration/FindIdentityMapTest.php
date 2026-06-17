<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityState;
use Articulate\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity(tableName: 'find_identity_map_users')]
class FindIdentityMapUser {
    #[PrimaryKey]
    #[AutoIncrement]
    public ?int $id = null;

    #[Property]
    public string $name;
}

class FindIdentityMapTest extends AbstractTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $connection->executeQuery('DROP TABLE IF EXISTS find_identity_map_users');

            if ($databaseName === 'mysql') {
                $connection->executeQuery('CREATE TABLE find_identity_map_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)');
            } else {
                $connection->executeQuery('CREATE TABLE find_identity_map_users (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL)');
            }

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS find_identity_map_users');
    }

    /** @return array<array{string}> */
    public static function databaseProvider(): array
    {
        return [['mysql'], ['pgsql']];
    }

    #[DataProvider('databaseProvider')]
    public function testFindReturnsSameInstanceAfterSetActiveUnitOfWork(string $databaseName): void
    {
        if (!$this->isDatabaseAvailable($databaseName)) {
            $this->markTestSkipped("{$databaseName} not available");
        }

        $connection = $this->getConnection($databaseName);
        $em = new EntityManager($connection);

        $user = new FindIdentityMapUser();
        $user->name = 'Alice';

        $em->persist($user);
        $em->flush();

        $id = $user->id;
        $this->assertNotNull($id);

        $initialUow = $em->getActiveUnitOfWork();
        $newUow = $em->createUnitOfWork();
        $em->setActiveUnitOfWork($newUow);
        $em->removeUnitOfWork($initialUow);

        $first = $em->find(FindIdentityMapUser::class, $id);
        $second = $em->find(FindIdentityMapUser::class, $id);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first, $second, 'find() must return the same object instance (identity map)');
        $this->assertSame('Alice', $first->name);
        $this->assertSame(EntityState::MANAGED, $newUow->getEntityState($first));
        $this->assertSame(EntityState::NEW, $initialUow->getEntityState($first));
    }

    #[DataProvider('databaseProvider')]
    public function testFindReturnsSameInstanceOnInitialUoW(string $databaseName): void
    {
        if (!$this->isDatabaseAvailable($databaseName)) {
            $this->markTestSkipped("{$databaseName} not available");
        }

        $connection = $this->getConnection($databaseName);
        $em = new EntityManager($connection);

        $user = new FindIdentityMapUser();
        $user->name = 'Bob';

        $em->persist($user);
        $em->flush();

        $id = $user->id;
        $this->assertNotNull($id);

        $first = $em->find(FindIdentityMapUser::class, $id);
        $second = $em->find(FindIdentityMapUser::class, $id);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first, $second, 'find() must return the same object instance (identity map)');
    }
}
