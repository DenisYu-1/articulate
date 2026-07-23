<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Modules\EntityManager\EntityDependencySorter;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;

// Mock entities for testing
#[Entity]
class MockUser {
    #[Property]
    public int $id;

    #[Property]
    public string $name;
}

#[Entity]
class MockPhone {
    #[Property]
    public int $id;

    #[ManyToOne(targetEntity: MockUser::class, referencedBy: 'phones')]
    public MockUser $user;
}

#[Entity]
class MockCart {
    #[Property]
    public int $id;

    #[OneToOne(ownedBy: 'user', targetEntity: MockUser::class)]
    public MockUser $user;
}

// Entities for foreignKey=false mutation test (Group 8)
#[Entity(tableName: 'fk_sort_parents')]
class FkSortParent {
    #[PrimaryKey]
    public int $id;
}

#[Entity(tableName: 'fk_sort_children')]
class FkSortChild {
    #[PrimaryKey]
    public int $id;

    /**
     * ManyToOne with foreignKey:false — isForeignKeyRequired() returns false.
     * The sorter must NOT add any ordering dependency for this relation.
     */
    #[ManyToOne(targetEntity: FkSortParent::class, foreignKey: false)]
    public ?FkSortParent $parent = null;
}

/**
 * Unit test for foreign key constraint ordering logic.
 *
 * This test verifies that the EntityManager correctly orders operations
 * to respect foreign key constraints without requiring actual database connectivity.
 */
class ForeignKeyOrderingTest extends TestCase {
    private EntityDependencySorter $dependencySorter;

    protected function setUp(): void
    {
        $this->dependencySorter = new EntityDependencySorter(new EntityMetadataRegistry());
    }

    public function testOrderEntitiesByDependenciesForInserts(): void
    {
        // Create test entities
        $user = new MockUser();
        $user->id = 1;
        $user->name = 'John Doe';

        $phone = new MockPhone();
        $phone->id = 2;
        $phone->user = $user;

        $cart = new MockCart();
        $cart->id = 3;
        $cart->user = $user;

        $entities = [$phone, $user, $cart]; // Intentionally out of order

        $ordered = $this->dependencySorter->order($entities, 'insert');

        // Verify that User (parent) comes before Phone and Cart (children)
        $userIndex = array_search($user, $ordered, true);
        $phoneIndex = array_search($phone, $ordered, true);
        $cartIndex = array_search($cart, $ordered, true);

        $this->assertLessThan($phoneIndex, $userIndex, 'User should be inserted before Phone');
        $this->assertLessThan($cartIndex, $userIndex, 'User should be inserted before Cart');
    }

    public function testOrderEntitiesByDependenciesForDeletes(): void
    {
        // Create test entities
        $user = new MockUser();
        $user->id = 1;

        $phone = new MockPhone();
        $phone->id = 2;
        $phone->user = $user;

        $entities = [$user, $phone]; // Intentionally out of order

        $ordered = $this->dependencySorter->order($entities, 'delete');

        // Verify that Phone (child) comes before User (parent)
        $userIndex = array_search($user, $ordered, true);
        $phoneIndex = array_search($phone, $ordered, true);

        $this->assertLessThan($userIndex, $phoneIndex, 'Phone should be deleted before User');
    }

    public function testBuildDependencyGraph(): void
    {
        // Create test entities
        $user = new MockUser();
        $phone = new MockPhone();
        $phone->user = $user;

        $entities = [$user, $phone];

        $graph = $this->dependencySorter->buildDependencyGraph($entities, 'insert');

        // Phone should depend on User for inserts
        $this->assertArrayHasKey(MockPhone::class, $graph);
        $this->assertContains(MockUser::class, $graph[MockPhone::class]);

        // User should not depend on Phone
        $this->assertArrayHasKey(MockUser::class, $graph);
        $this->assertNotContains(MockPhone::class, $graph[MockUser::class]);

        // Test delete graph
        $deleteGraph = $this->dependencySorter->buildDependencyGraph($entities, 'delete');

        // User should depend on Phone for deletes (children deleted first)
        $this->assertArrayHasKey(MockUser::class, $deleteGraph);
        $this->assertContains(MockPhone::class, $deleteGraph[MockUser::class]);
    }

    public function testTopologicalSort(): void
    {
        $entities = [
            new class() {
                public function __toString()
                {
                    return 'A';
                }
            },
            new class() {
                public function __toString()
                {
                    return 'B';
                }
            },
            new class() {
                public function __toString()
                {
                    return 'C';
                }
            },
        ];

        // Graph: A depends on B, B depends on C
        $graph = [
            'class@anonymous' => ['class@anonymous'], // A depends on B
        ];

        $result = $this->dependencySorter->topologicalSort($entities, $graph);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    // ── `&&` → `||` mutation killer ───────────────────

    public function testInsertWithNoForeignKeyDoesNotReverseOrder(): void
    {
        $parent = new FkSortParent();
        $parent->id = 1;

        $child = new FkSortChild();
        $child->id = 2;
        $child->parent = $parent;

        // Input order: parent first, child second.
        $ordered = $this->dependencySorter->order([$parent, $child], 'insert');

        $parentIndex = array_search($parent, $ordered, true);
        $childIndex  = array_search($child, $ordered, true);

        // Without a FK constraint no dependency is injected, so the topological
        // sort preserves the original iteration order: parent before child.
        $this->assertLessThan(
            $childIndex,
            $parentIndex,
            'Insert with foreignKey:false must not impose reverse (delete-style) ordering',
        );
    }

    public function testDeleteWithNoForeignKeyDoesNotAddUnnecessaryDependency(): void
    {
        $parent = new FkSortParent();
        $parent->id = 1;

        $child = new FkSortChild();
        $child->id = 2;
        $child->parent = $parent;

        // Verify that the dependency graph has NO dependency for foreignKey:false delete.
        $graph = $this->dependencySorter->buildDependencyGraph([$parent, $child], 'delete');

        $this->assertSame(
            [],
            $graph[FkSortParent::class],
            'No delete dependency must be added when isForeignKeyRequired is false',
        );
        $this->assertSame(
            [],
            $graph[FkSortChild::class],
            'No delete dependency must be added for the child either',
        );
    }
}
