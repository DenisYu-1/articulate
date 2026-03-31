<?php

namespace Articulate\Tests\Integration;

use Articulate\Connection;
use Articulate\Exceptions\EntityNotFoundException;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\DatabaseTestTrait;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntityMissing;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntityRefresh;
use PHPUnit\Framework\Attributes\DataProvider;

class EntityManagerMethodsIntegrationTest extends AbstractTestCase {
    use DatabaseTestTrait;

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $this->createEntityTable($connection, $databaseName, 'test_entity');
            $this->createEntityTable($connection, $databaseName, 'test_entity_refresh');
            $this->createEntityTable($connection, $databaseName, 'test_entity_missing');

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $dropSuffix = $databaseName === 'pgsql' ? ' CASCADE' : '';
        $quote = $databaseName === 'pgsql' ? '"' : '`';

        foreach (['test_entity', 'test_entity_refresh', 'test_entity_missing'] as $table) {
            try {
                $connection->executeQuery("DROP TABLE IF EXISTS {$quote}{$table}{$quote}{$dropSuffix}");
            } catch (\Exception) {
            }
        }
    }

    private function createEntityTable(Connection $connection, string $databaseName, string $tableName): void
    {
        $sql = match ($databaseName) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `{$tableName}` (id INT PRIMARY KEY, name VARCHAR(255))",
            'pgsql' => "CREATE TABLE IF NOT EXISTS \"{$tableName}\" (id INTEGER PRIMARY KEY, name VARCHAR(255))",
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}"),
        };

        $connection->executeQuery($sql);
    }

    #[DataProvider('databaseProvider')]
    public function testGetReferenceReturnsProxyWithoutDatabaseQuery(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $entityManager = new EntityManager($connection);

        $proxy = $entityManager->getReference(TestEntity::class, 1);

        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertFalse($proxy->isProxyInitialized());
        $this->assertEquals(1, $proxy->getProxyIdentifier());
    }

    #[DataProvider('databaseProvider')]
    public function testRefreshUpdatesEntityWithFreshData(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $entityManager = new EntityManager($connection);

        $quote = $databaseName === 'pgsql' ? '"' : '`';
        $connection->executeQuery("INSERT INTO {$quote}test_entity_refresh{$quote} (id, name) VALUES (1, 'original')");

        $entity = $entityManager->find(TestEntityRefresh::class, 1);
        $this->assertNotNull($entity);
        $this->assertEquals('original', $entity->name);

        $entity->name = 'modified';

        $connection->executeQuery("UPDATE {$quote}test_entity_refresh{$quote} SET name = 'external_change' WHERE id = 1");

        $entityManager->refresh($entity);

        $this->assertEquals(1, $entity->id);
        $this->assertEquals('external_change', $entity->name);
    }

    #[DataProvider('databaseProvider')]
    public function testRefreshThrowsExceptionForNonExistentEntity(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $entityManager = new EntityManager($connection);

        $entity = new TestEntityMissing();
        $entity->id = 999;

        $this->expectException(EntityNotFoundException::class);
        $entityManager->refresh($entity);
    }
}
