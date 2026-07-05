<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class UuidOrder {
    #[PrimaryKey(type: 'string', generator: PrimaryKey::GENERATOR_UUID_V4)]
    public ?string $id = null;

    #[Property]
    public string $name;
}

class UuidPrimaryKeyPersistTest extends AbstractTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $connection->executeQuery('DROP TABLE IF EXISTS uuid_order');
            $connection->executeQuery('CREATE TABLE uuid_order (id VARCHAR(36) PRIMARY KEY, name VARCHAR(255) NOT NULL)');

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS uuid_order');
    }

    public function testUuidAssignedAtFlushTime(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection) {
            $em = new EntityManager($connection);
            $order = new UuidOrder();
            $order->name = 'test';

            $em->persist($order);

            $this->assertNull($order->id, 'UUID must not be assigned by persist(), before flush()');

            $em->flush();

            $this->assertNotNull($order->id, 'UUID must be assigned during flush()');
        });
    }

    public function testUuidAssignedDuringFlush(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection) {
            $em = new EntityManager($connection);
            $order = new UuidOrder();
            $order->name = 'test';

            $em->persist($order);

            $this->assertNull($order->id, 'UUID must not be assigned before flush()');

            $em->flush();

            $this->assertNotNull($order->id, 'UUID must be assigned during flush()');
        });
    }

    private function runTestForAllDatabases(callable $fn): void
    {
        foreach (['mysql', 'pgsql'] as $db) {
            if (!$this->isDatabaseAvailable($db)) {
                continue;
            }

            $fn($this->getConnection($db), $db);
        }
    }
}
