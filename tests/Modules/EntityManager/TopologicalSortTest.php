<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'topo_node_a')]
class TopoNodeA {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[ManyToOne(targetEntity: TopoNodeB::class)]
    public TopoNodeB $b;
}

#[Entity(tableName: 'topo_node_b')]
class TopoNodeB {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[ManyToOne(targetEntity: TopoNodeA::class)]
    public TopoNodeA $a;
}

class TopologicalSortTest extends TestCase {
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $connection = $this->createStub(Connection::class);
        $this->entityManager = new EntityManager($connection);
    }

    private function callOrderEntitiesByDependencies(array $entities, string $operation): array
    {
        $reflection = new \ReflectionClass($this->entityManager);
        $method = $reflection->getMethod('orderEntitiesByDependencies');
        $method->setAccessible(true);

        return $method->invoke($this->entityManager, $entities, $operation);
    }

    public function testCircularDependencyExceptionIncludesBothClassNames(): void
    {
        $a = new TopoNodeA();
        $a->id = 1;
        $a->name = 'a';

        $b = new TopoNodeB();
        $b->id = 2;
        $b->name = 'b';

        $a->b = $b;
        $b->a = $a;

        $this->expectException(\RuntimeException::class);

        try {
            $this->callOrderEntitiesByDependencies([$a, $b], 'insert');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString(TopoNodeA::class, $message);
            $this->assertStringContainsString(TopoNodeB::class, $message);
            $this->assertStringContainsString('→', $message);

            throw $e;
        }
    }

    public function testCircularDependencyExceptionMessageFormat(): void
    {
        $a = new TopoNodeA();
        $a->id = 1;
        $a->name = 'a';

        $b = new TopoNodeB();
        $b->id = 2;
        $b->name = 'b';

        $a->b = $b;
        $b->a = $a;

        try {
            $this->callOrderEntitiesByDependencies([$a, $b], 'insert');
            $this->fail('Expected RuntimeException for circular dependency');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Circular dependency detected:', $e->getMessage());
            $this->assertStringContainsString('Check your entity FK relationships.', $e->getMessage());
        }
    }
}
