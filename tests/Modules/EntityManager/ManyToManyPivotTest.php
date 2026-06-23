<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Collection\MappingCollection;
use Articulate\Collection\MappingItem;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\HydratorInterface;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Group;

// ── Fixture entities ──────────────────────────────────────────────────────────

#[Entity(tableName: 'pvt_test_users')]
class PivotTestUser {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[ManyToMany(
        targetEntity: PivotTestPerm::class,
        referencedBy: 'users',
        mappingTable: new MappingTable(
            name: 'pvt_test_user_perms',
            properties: [new MappingTableProperty(name: 'role')]
        )
    )]
    public ?MappingCollection $permissions = null;
}

#[Entity(tableName: 'pvt_test_perms')]
class PivotTestPerm {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $label;
}

// ── Test class ────────────────────────────────────────────────────────────────

class ManyToManyPivotTest extends DatabaseTestCase {
    private RelationshipLoader $loader;

    private EntityManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function buildLoader(): void
    {
        $hydrator = new class() implements HydratorInterface {
            public function hydrate(string $class, array $data, ?object $entity = null, array $with = []): mixed
            {
                $entity ??= new $class();
                $r = new \ReflectionClass($entity);
                foreach ($data as $col => $value) {
                    $prop = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $col))));
                    if ($r->hasProperty($prop)) {
                        $r->getProperty($prop)->setAccessible(true);
                        $r->getProperty($prop)->setValue($entity, $value);
                    }
                }

                return $entity;
            }

            public function extract(mixed $entity): array
            {
                $data = [];
                $r = new \ReflectionClass($entity);
                foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                    $data[$p->getName()] = $entity->{$p->getName()} ?? null;
                }

                return $data;
            }

            public function hydratePartial(object $entity, array $data): void
            {
                foreach ($data as $k => $v) {
                    if (property_exists($entity, $k)) {
                        $entity->$k = $v;
                    }
                }
            }
        };

        $this->entityManager = new EntityManager($this->currentConnection, hydrator: $hydrator);
        $this->loader = new RelationshipLoader($this->entityManager, $this->entityManager->getMetadataRegistry());
    }

    private function insertRow(string $table, array $values): int
    {
        $cols = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->currentConnection->executeQuery(
            "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})",
            array_values($values)
        );

        $result = $this->currentConnection->executeQuery('SELECT LAST_INSERT_ID() as id');

        return (int) $result->fetch()['id'];
    }

    #[Group('database')]
    public function testMappingCollectionReceivesPivotData(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['pvt_test_user_perms', 'pvt_test_users', 'pvt_test_perms']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE pvt_test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE pvt_test_perms (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(255) NOT NULL)'
        );
        // FK columns derived from entity table names: pvt_test_users_id, pvt_test_perms_id
        $this->currentConnection->executeQuery(
            'CREATE TABLE pvt_test_user_perms (pvt_test_users_id INT NOT NULL, pvt_test_perms_id INT NOT NULL, role VARCHAR(50) NOT NULL)'
        );

        $userId = $this->insertRow('pvt_test_users', ['name' => 'Alice']);
        $perm1Id = $this->insertRow('pvt_test_perms', ['label' => 'read']);
        $perm2Id = $this->insertRow('pvt_test_perms', ['label' => 'write']);

        $this->currentConnection->executeQuery(
            'INSERT INTO pvt_test_user_perms (pvt_test_users_id, pvt_test_perms_id, role) VALUES (?, ?, ?)',
            [$userId, $perm1Id, 'viewer']
        );
        $this->currentConnection->executeQuery(
            'INSERT INTO pvt_test_user_perms (pvt_test_users_id, pvt_test_perms_id, role) VALUES (?, ?, ?)',
            [$userId, $perm2Id, 'editor']
        );

        $user = new PivotTestUser();
        $user->id = $userId;
        $user->name = 'Alice';

        $relation = (new EntityMetadata(PivotTestUser::class))->getRelation('permissions');
        $this->assertNotNull($relation);
        $this->assertInstanceOf(ReflectionManyToMany::class, $relation);

        $result = $this->loader->load($user, $relation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(MappingItem::class, $result);

        $collection = new MappingCollection($result);

        $this->assertCount(2, $collection);
        $this->assertInstanceOf(PivotTestPerm::class, $collection->first()->entity);

        // Pivot data is accessible
        $roles = array_map(fn (MappingItem $item) => $item->pivotValue('role'), $result);
        $this->assertContains('viewer', $roles);
        $this->assertContains('editor', $roles);

        // Related entity IDs match
        $permIds = array_map(fn (MappingItem $item) => $item->entity->id, $result);
        $this->assertContains($perm1Id, $permIds);
        $this->assertContains($perm2Id, $permIds);
    }

    #[Group('database')]
    public function testMappingCollectionEmptyWhenNoPivotRows(): void
    {
        $this->setCurrentDatabase($this->getConnection('mysql'), 'mysql');
        $this->buildLoader();
        $this->cleanUpTables(['pvt_test_user_perms', 'pvt_test_users', 'pvt_test_perms']);

        $this->currentConnection->executeQuery(
            'CREATE TABLE pvt_test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE pvt_test_perms (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(255) NOT NULL)'
        );
        $this->currentConnection->executeQuery(
            'CREATE TABLE pvt_test_user_perms (pvt_test_users_id INT NOT NULL, pvt_test_perms_id INT NOT NULL, role VARCHAR(50) NOT NULL)'
        );

        $userId = $this->insertRow('pvt_test_users', ['name' => 'Bob']);

        $user = new PivotTestUser();
        $user->id = $userId;
        $user->name = 'Bob';

        $relation = (new EntityMetadata(PivotTestUser::class))->getRelation('permissions');
        $this->assertNotNull($relation);

        $result = $this->loader->load($user, $relation);

        $this->assertSame([], $result);
    }
}
