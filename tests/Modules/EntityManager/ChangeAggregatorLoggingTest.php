<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\ChangeAggregator;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class CollectingLogger extends AbstractLogger {
    /** @var array<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}

#[Entity]
class LoggingTestEntity {
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name = '';
}

class ChangeAggregatorLoggingTest extends TestCase {
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $connection = $this->createStub(Connection::class);
        $this->entityManager = new EntityManager($connection);
    }

    public function testNoLogWhenNoOverlappingChanges(): void
    {
        $logger = new CollectingLogger();
        $metadataRegistry = $this->entityManager->getMetadataRegistry();
        $aggregator = new ChangeAggregator(
            $metadataRegistry,
            $this->entityManager->getUpdateConflictResolutionStrategy(),
            $logger,
        );

        $uow = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entity = new LoggingTestEntity();
        $entity->id = 1;
        $entity->name = 'Alice';
        $uow->registerManaged($entity, ['id' => 1, 'name' => 'Alice']);
        $entity->name = 'Bob';

        $aggregator->aggregateChanges([$uow]);

        $this->assertEmpty($logger->records);
    }

    public function testDebugLogEmittedOnOverlappingKeys(): void
    {
        $logger = new CollectingLogger();
        $metadataRegistry = $this->entityManager->getMetadataRegistry();
        $aggregator = new ChangeAggregator(
            $metadataRegistry,
            $this->entityManager->getUpdateConflictResolutionStrategy(),
            $logger,
        );

        // Two UoWs both update the same entity's same field → overlapping key on merge
        $uow1 = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entity1 = new LoggingTestEntity();
        $entity1->id = 5;
        $entity1->name = 'Original';
        $uow1->registerManaged($entity1, ['id' => 5, 'name' => 'Original']);
        $entity1->name = 'FirstChange';

        $uow2 = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entity2 = new LoggingTestEntity();
        $entity2->id = 5;
        $entity2->name = 'Original';
        $uow2->registerManaged($entity2, ['id' => 5, 'name' => 'Original']);
        $entity2->name = 'SecondChange';

        $aggregator->aggregateChanges([$uow1, $uow2]);

        $debugLogs = array_filter($logger->records, fn ($r) => $r['level'] === 'debug');
        $this->assertNotEmpty($debugLogs);

        $found = false;
        foreach ($debugLogs as $log) {
            if (str_contains($log['message'], 'overlapping')) {
                $found = true;

                break;
            }
        }
        $this->assertTrue($found, 'Expected a debug log about overlapping changes');
    }

    public function testNoLoggerDoesNotCrash(): void
    {
        $metadataRegistry = $this->entityManager->getMetadataRegistry();
        $aggregator = new ChangeAggregator(
            $metadataRegistry,
            $this->entityManager->getUpdateConflictResolutionStrategy(),
        );

        $uow1 = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entity1 = new LoggingTestEntity();
        $entity1->id = 7;
        $entity1->name = 'A';
        $uow1->registerManaged($entity1, ['id' => 7, 'name' => 'A']);
        $entity1->name = 'B';

        $uow2 = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entity2 = new LoggingTestEntity();
        $entity2->id = 7;
        $entity2->name = 'A';
        $uow2->registerManaged($entity2, ['id' => 7, 'name' => 'A']);
        $entity2->name = 'C';

        $result = $aggregator->aggregateChanges([$uow1, $uow2]);
        $this->assertCount(1, $result['updates']);
    }
}
