<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Entity;
use Articulate\Exceptions\EntityNotFoundException;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\DatabaseTestTrait;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntityMissing;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntityRefresh;

/**
 * Integration test for EntityManager getReference() and refresh() methods.
 */
class EntityManagerMethodsIntegrationTest extends AbstractTestCase {
    use DatabaseTestTrait;

    public function testGetReferenceReturnsProxyWithoutDatabaseQuery(): void
    {
        $connection = $this->getConnection('mysql');
        $entityManager = new EntityManager($connection);

        // Create table for TestEntity
        $this->createTestEntityTable($connection, 'test_entity');

        // getReference should return a proxy without hitting the database
        $proxy = $entityManager->getReference(TestEntity::class, 1);

        // Verify it's a proxy
        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertTrue($proxy->isProxyInitialized() === false);

        // Verify it has the correct ID
        $this->assertEquals(1, $proxy->getProxyIdentifier());
    }

    public function testRefreshUpdatesEntityWithFreshData(): void
    {
        $connection = $this->getConnection('mysql');
        $entityManager = new EntityManager($connection);

        // Create table and insert test data
        $tableName = $this->createTestEntityTable($connection, 'test_entity_refresh');
        $connection->executeQuery("INSERT INTO `{$tableName}` (id, name) VALUES (1, 'original')");

        // Load entity
        $entity = $entityManager->find(TestEntityRefresh::class, 1);
        $this->assertNotNull($entity);
        $this->assertEquals(1, $entity->id);
        $this->assertEquals('original', $entity->name);

        // Modify entity in memory (but don't flush)
        $entity->name = 'modified';

        // Manually update the database to simulate external change
        $connection->executeQuery("UPDATE `{$tableName}` SET name = 'external_change' WHERE id = 1");

        // Refresh should reload the data from database
        $entityManager->refresh($entity);

        // Entity should have the external change
        $this->assertEquals(1, $entity->id);
        $this->assertEquals('external_change', $entity->name);
    }

    public function testRefreshThrowsExceptionForNonExistentEntity(): void
    {
        $connection = $this->getConnection('mysql');
        $entityManager = new EntityManager($connection);

        // Create table but don't insert any data
        $this->createTestEntityTable($connection, 'test_entity_missing');

        // Create an entity instance manually
        $entity = new TestEntityMissing();
        $entity->id = 999; // Non-existent ID

        // refresh should throw EntityNotFoundException
        $this->expectException(EntityNotFoundException::class);
        $entityManager->refresh($entity);
    }

    private function createTestEntityTable($connection, string $tableName): string
    {
        // Use table name as-is since the entities have explicit table names configured
        $connection->executeQuery("DROP TABLE IF EXISTS `{$tableName}`");
        $connection->executeQuery("
            CREATE TABLE `{$tableName}` (
                id INT PRIMARY KEY,
                name VARCHAR(255)
            )
        ");

        return $tableName;
    }
}
