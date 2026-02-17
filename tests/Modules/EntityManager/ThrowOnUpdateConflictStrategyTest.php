<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Exceptions\UpdateConflictException;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\ThrowOnUpdateConflictStrategy;
use PHPUnit\Framework\TestCase;

#[Entity]
class ConflictEntityAlpha {
    #[Property]
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;
}

class ThrowOnUpdateConflictStrategyTest extends TestCase {
    private EntityManager $entityManager;

    private ThrowOnUpdateConflictStrategy $strategy;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->entityManager = new EntityManager($connection);
        $this->strategy = new ThrowOnUpdateConflictStrategy();
    }

    public function testThrowsOnDuplicateUpdateForSameRow(): void
    {
        $entity = new ConflictEntityAlpha();
        $entity->id = 1;
        $entity->name = 'First';

        $updates = [
            ['entity' => $entity, 'changes' => ['name' => 'First Update']],
            ['entity' => $entity, 'changes' => ['name' => 'Second Update']],
        ];

        $this->expectException(UpdateConflictException::class);

        $this->strategy->resolve($updates, $this->entityManager->getMetadataRegistry());
    }

    public function testThrowsOnDuplicateUpdateForSameRowWithDifferentInstances(): void
    {
        $entityOne = new ConflictEntityAlpha();
        $entityOne->id = 10;
        $entityOne->name = 'Original';

        $entityTwo = new ConflictEntityAlpha();
        $entityTwo->id = 10;
        $entityTwo->name = 'Original';

        $updates = [
            ['entity' => $entityOne, 'changes' => ['name' => 'One']],
            ['entity' => $entityTwo, 'changes' => ['name' => 'Two']],
        ];

        $this->expectException(UpdateConflictException::class);

        $this->strategy->resolve($updates, $this->entityManager->getMetadataRegistry());
    }

    public function testAllowsUpdatesForDifferentRows(): void
    {
        $entityOne = new ConflictEntityAlpha();
        $entityOne->id = 1;
        $entityOne->name = 'One';

        $entityTwo = new ConflictEntityAlpha();
        $entityTwo->id = 2;
        $entityTwo->name = 'Two';

        $updates = [
            ['entity' => $entityOne, 'changes' => ['name' => 'Updated One']],
            ['entity' => $entityTwo, 'changes' => ['name' => 'Updated Two']],
        ];

        $result = $this->strategy->resolve($updates, $this->entityManager->getMetadataRegistry());

        $this->assertSame($updates, $result);
    }
}
