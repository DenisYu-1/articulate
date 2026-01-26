<?php

namespace Articulate\Tests\EdgeCases;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Entity(tableName: 'shared_entities')]
class SharedTableAlpha {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public string $description;
}

#[Entity(tableName: 'shared_entities')]
class SharedTableBeta {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public int $status = 0;
}

class SharedTableEntitiesTest extends DatabaseTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        $this->createSharedTable($connection, $databaseName);

        return true;
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $this->dropSharedTable($connection, $databaseName);
    }

    #[DataProvider('databaseProvider')]
    #[Group('database')]
    public function testTwoEntitiesSharingTableFlushUpdates(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        $entityManager = new EntityManager($connection);

        $alpha = new SharedTableAlpha();
        $alpha->name = 'Initial Name';
        $alpha->description = 'Initial Description';

        $entityManager->persist($alpha);
        $entityManager->flush();

        $this->assertNotNull($alpha->id);

        $alphaId = $alpha->id;

        $selectedAlpha = $entityManager->find(SharedTableAlpha::class, $alphaId);
        $selectedBeta = $entityManager->find(SharedTableBeta::class, $alphaId);

        $this->assertNotNull($selectedAlpha);
        $this->assertNotNull($selectedBeta);

        $sharedName = 'Shared Updated Name';

        $selectedAlpha->name = $sharedName;
        $selectedAlpha->description = 'Updated Description';
        $selectedBeta->name = $sharedName;
        $selectedBeta->status = 5;

        $entityManager->flush();
        $entityManager->clear();

        $reloadedAlpha = $entityManager->find(SharedTableAlpha::class, $alphaId);
        $reloadedBeta = $entityManager->find(SharedTableBeta::class, $alphaId);

        $this->assertNotNull($reloadedAlpha);
        $this->assertNotNull($reloadedBeta);
        $this->assertSame($sharedName, $reloadedAlpha->name);
        $this->assertSame('Updated Description', $reloadedAlpha->description);
        $this->assertSame($sharedName, $reloadedBeta->name);
        $this->assertSame(5, $reloadedBeta->status);
    }

    private function createSharedTable(Connection $connection, string $databaseName): void
    {
        $this->dropSharedTable($connection, $databaseName);

        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE `shared_entities` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                status INT NOT NULL DEFAULT 0
            )',
            'pgsql' => 'CREATE TABLE "shared_entities" (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                status INTEGER NOT NULL DEFAULT 0
            )',
        };

        $connection->executeQuery($sql);
    }

    private function dropSharedTable(Connection $connection, string $databaseName): void
    {
        $dropSql = match ($databaseName) {
            'mysql' => 'DROP TABLE IF EXISTS `shared_entities`',
            'pgsql' => 'DROP TABLE IF EXISTS "shared_entities"',
        };

        $connection->executeQuery($dropSql);
    }
}
