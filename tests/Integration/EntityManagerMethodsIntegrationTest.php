<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Entity;
use Articulate\Exceptions\EntityNotFoundException;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;

/**
 * Integration test for EntityManager getReference() and refresh() methods.
 */
class EntityManagerMethodsIntegrationTest extends AbstractTestCase {
    public function testGetReferenceReturnsProxyWithoutDatabaseQuery(): void
    {
        $this->skipIfDatabaseNotAvailable('mysql');
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
        $this->assertEquals(1, $proxy->_getIdentifier());
    }

    public function testRefreshUpdatesEntityWithFreshData(): void
    {
        $this->skipIfDatabaseNotAvailable('mysql');
        $connection = $this->getConnection('mysql');
        $entityManager = new EntityManager($connection);

        // Create table and insert test data
        $tableName = $this->createTestEntityTable($connection, 'test_entity_refresh');
        $connection->executeQuery("INSERT INTO `{$tableName}` (id) VALUES (1)");

        // Load entity
        $entity = $entityManager->find(TestEntity::class, 1);
        $this->assertEquals(1, $entity->id);

        // Modify entity in memory (but don't flush)
        $entity->id = 999; // This shouldn't be allowed, but for testing

        // Manually update the database to simulate external change
        $connection->executeQuery("UPDATE `{$tableName}` SET id = 1 WHERE id = 1");

        // Refresh should reload the original data
        $entityManager->refresh($entity);

        // Entity should have the original data
        $this->assertEquals(1, $entity->id);
    }

    public function testRefreshThrowsExceptionForNonExistentEntity(): void
    {
        $this->skipIfDatabaseNotAvailable('mysql');
        $connection = $this->getConnection('mysql');
        $entityManager = new EntityManager($connection);

        // Create table but don't insert any data
        $this->createTestEntityTable($connection, 'test_entity_missing');

        // Create an entity instance manually
        $entity = new TestEntity();
        $entity->id = 999; // Non-existent ID

        // refresh should throw EntityNotFoundException
        $this->expectException(EntityNotFoundException::class);
        $entityManager->refresh($entity);
    }

    private function createTestEntityTable($connection, string $tableName): string
    {
        $tableName = $this->getTableName($tableName, 'mysql');
        $connection->executeQuery("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                id INT PRIMARY KEY
            )
        ");

        return $tableName;
    }
}
