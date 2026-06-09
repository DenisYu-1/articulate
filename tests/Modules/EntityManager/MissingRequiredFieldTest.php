<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\AbstractTestCase;
use Exception;

#[Entity(tableName: 'missing_field_test')]
class PartialEntity {
    #[PrimaryKey]
    public ?int $id = null;
    // 'required_field' column exists in the table but is not mapped here
}

#[Entity(tableName: 'entity_with_db_default')]
class EntityWithDbDefault {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;
    // 'audit_col' exists in DB with DEFAULT 'system' — not mapped, DB fills it in
}

class MissingRequiredFieldTest extends AbstractTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $connection->executeQuery('DROP TABLE IF EXISTS missing_field_test');
            $connection->executeQuery('DROP TABLE IF EXISTS entity_with_db_default');

            $sql = match ($databaseName) {
                'mysql' => 'CREATE TABLE missing_field_test (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    required_field VARCHAR(255) NOT NULL
                )',
                'pgsql' => 'CREATE TABLE missing_field_test (
                    id SERIAL PRIMARY KEY,
                    required_field VARCHAR(255) NOT NULL
                )',
                default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
            };
            $connection->executeQuery($sql);

            $sqlDefault = match ($databaseName) {
                'mysql' => "CREATE TABLE entity_with_db_default (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    audit_col VARCHAR(255) NOT NULL DEFAULT 'system'
                )",
                'pgsql' => "CREATE TABLE entity_with_db_default (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    audit_col VARCHAR(255) NOT NULL DEFAULT 'system'
                )",
                default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
            };
            $connection->executeQuery($sqlDefault);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS missing_field_test');
        $connection->executeQuery('DROP TABLE IF EXISTS entity_with_db_default');
    }

    public function testInsertEntityMissingNonNullableColumnThrowsException(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $em = new EntityManager($connection);

            $entity = new PartialEntity();
            $em->persist($entity);

            $this->expectException(\PDOException::class);
            $em->flush();
        });
    }

    public function testInsertEntityWithUnmappedColumnWithDbDefaultSucceeds(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $em = new EntityManager($connection);

            $entity = new EntityWithDbDefault();
            $entity->name = 'test';
            $em->persist($entity);
            $em->flush();

            $this->assertNotNull($entity->id);
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
