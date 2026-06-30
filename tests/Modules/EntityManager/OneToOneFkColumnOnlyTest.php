<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\AbstractTestCase;
use Exception;

#[Entity(tableName: 'fk_only_targets')]
class FkOnlyTarget {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $label = '';
}

// Owning side: FK column declared via #[OneToOne(column:)] only — no #[Property(name:'target_id')].
#[Entity(tableName: 'fk_only_owners')]
class FkOnlyOwner {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name = 'owner';

    #[OneToOne(targetEntity: FkOnlyTarget::class, column: 'target_id', lazy: true)]
    public ?FkOnlyTarget $target = null;
}

class OneToOneFkColumnOnlyTest extends AbstractTestCase {
    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $connection->executeQuery('DROP TABLE IF EXISTS fk_only_owners');
            $connection->executeQuery('DROP TABLE IF EXISTS fk_only_targets');

            $targetSql = match ($databaseName) {
                'mysql' => 'CREATE TABLE fk_only_targets (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(255) NOT NULL)',
                'pgsql' => 'CREATE TABLE fk_only_targets (id SERIAL PRIMARY KEY, label VARCHAR(255) NOT NULL)',
                default => throw new \InvalidArgumentException("Unsupported: {$databaseName}"),
            };
            $ownerSql = match ($databaseName) {
                'mysql' => 'CREATE TABLE fk_only_owners (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL DEFAULT \'owner\', target_id INT NULL)',
                'pgsql' => 'CREATE TABLE fk_only_owners (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL DEFAULT \'owner\', target_id INT NULL)',
                default => throw new \InvalidArgumentException("Unsupported: {$databaseName}"),
            };

            $connection->executeQuery($targetSql);
            $connection->executeQuery($ownerSql);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS fk_only_owners');
        $connection->executeQuery('DROP TABLE IF EXISTS fk_only_targets');
    }

    /**
     * Persist Owner→Target with FK only in #[OneToOne(column:)], clear EM,
     * reload Owner, then loadRelation must return the correct Target.
     */
    public function testLoadRelationOneToOneViaFkColumnOnly(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $em = new EntityManager($connection);

            $target = new FkOnlyTarget();
            $target->label = 'hello';

            $owner = new FkOnlyOwner();
            $owner->target = $target;

            $em->persist($target);
            $em->persist($owner);
            $em->flush();

            $ownerId = $owner->id;
            $targetId = $target->id;
            $this->assertNotNull($ownerId);
            $this->assertNotNull($targetId);

            $em->clear();

            /** @var FkOnlyOwner $reloaded */
            $reloaded = $em->find(FkOnlyOwner::class, $ownerId);
            $this->assertInstanceOf(FkOnlyOwner::class, $reloaded);
            // lazy — not eagerly loaded
            $this->assertNull($reloaded->target);

            $loaded = $em->loadRelation($reloaded, 'target');

            $this->assertInstanceOf(FkOnlyTarget::class, $loaded);
            $this->assertEquals($targetId, $loaded->id);
            $this->assertEquals('hello', $loaded->label);
        });
    }

    /**
     * When owner has no target (target_id IS NULL), loadRelation must return null.
     */
    public function testLoadRelationOneToOneReturnsNullWhenNoFk(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $em = new EntityManager($connection);

            $owner = new FkOnlyOwner();

            $em->persist($owner);
            $em->flush();

            $ownerId = $owner->id;
            $em->clear();

            /** @var FkOnlyOwner $reloaded */
            $reloaded = $em->find(FkOnlyOwner::class, $ownerId);
            $this->assertNull($reloaded->target);

            $loaded = $em->loadRelation($reloaded, 'target');

            $this->assertNull($loaded);
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
