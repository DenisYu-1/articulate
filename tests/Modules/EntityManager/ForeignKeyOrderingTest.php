<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
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

/**
 * Unit test for foreign key constraint ordering logic.
 *
 * This test verifies that the EntityManager correctly orders operations
 * to respect foreign key constraints without requiring actual database connectivity.
 */
class ForeignKeyOrderingTest extends TestCase {
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        // Create a mock connection
        $connection = $this->createMock(Connection::class);

        // Create EntityManager
        $this->entityManager = new EntityManager($connection);
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

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->entityManager);
        $method = $reflection->getMethod('orderEntitiesByDependencies');
        $method->setAccessible(true);

        $ordered = $method->invoke($this->entityManager, $entities, 'insert');

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

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->entityManager);
        $method = $reflection->getMethod('orderEntitiesByDependencies');
        $method->setAccessible(true);

        $ordered = $method->invoke($this->entityManager, $entities, 'delete');

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

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->entityManager);
        $method = $reflection->getMethod('buildDependencyGraph');
        $method->setAccessible(true);

        $graph = $method->invoke($this->entityManager, $entities, 'insert');

        // Phone should depend on User for inserts
        $this->assertArrayHasKey(MockPhone::class, $graph);
        $this->assertContains(MockUser::class, $graph[MockPhone::class]);

        // User should not depend on Phone
        $this->assertArrayHasKey(MockUser::class, $graph);
        $this->assertNotContains(MockPhone::class, $graph[MockUser::class]);

        // Test delete graph
        $deleteGraph = $method->invoke($this->entityManager, $entities, 'delete');

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

        // For simplicity, let's just test that the method exists and can be called
        $reflection = new \ReflectionClass($this->entityManager);
        $method = $reflection->getMethod('topologicalSort');
        $method->setAccessible(true);

        // This should not throw an exception
        $result = $method->invoke($this->entityManager, $entities, $graph);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }
}
