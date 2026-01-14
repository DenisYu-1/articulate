<?php

namespace Articulate\Tests\Modules\Generators;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Modules\Generators\AutoIncrementGenerator;
use Articulate\Modules\Generators\CustomGenerator;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Modules\Generators\GeneratorStrategyInterface;
use Articulate\Modules\Generators\SerialGenerator;
use Articulate\Modules\Generators\Strategies\PrefixedIdGenerator;
use Articulate\Modules\Generators\UlidGenerator;
use Articulate\Modules\Generators\UuidGenerator;
use Articulate\Modules\Generators\UuidV7Generator;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class UuidEntity {
    #[PrimaryKey(generator: 'uuid_v4')]
    public ?string $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class UuidV7Entity {
    #[PrimaryKey(generator: 'uuid_v7')]
    public ?string $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class UlidEntity {
    #[PrimaryKey(generator: 'ulid')]
    public ?string $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class SerialEntity {
    #[PrimaryKey(generator: 'serial')]
    public ?int $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class PrefixedEntity {
    #[PrimaryKey(generator: 'prefixed_id', options: ['prefix' => 'USR_', 'length' => 6])]
    public ?string $id = null;

    #[Property]
    public string $name;
}

class GeneratorTest extends AbstractTestCase {
    public function testAutoIncrementGenerator(): void
    {
        $generator = new AutoIncrementGenerator();

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
    }

    public function testUuidGenerator(): void
    {
        $generator = new UuidGenerator();

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);

        // UUID v4 should be 36 characters with dashes
        $this->assertEquals(36, strlen($id1));
        $this->assertStringContainsString('-', $id1);
    }

    public function testUuidV7Generator(): void
    {
        $generator = new UuidV7Generator();

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);

        // UUID should be 36 characters with dashes
        $this->assertEquals(36, strlen($id1));
        $this->assertStringContainsString('-', $id1);
    }

    public function testUlidGenerator(): void
    {
        $generator = new UlidGenerator();

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);

        // ULID should be 26 characters
        $this->assertEquals(26, strlen($id1));
    }

    public function testSerialGenerator(): void
    {
        $generator = new SerialGenerator();

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
    }

    public function testGeneratorRegistry(): void
    {
        $registry = new GeneratorRegistry();

        $this->assertTrue($registry->hasGenerator('auto_increment'));
        $this->assertTrue($registry->hasGenerator('uuid_v4'));
        $this->assertTrue($registry->hasGenerator('uuid_v7'));
        $this->assertTrue($registry->hasGenerator('ulid'));
        $this->assertTrue($registry->hasGenerator('serial'));

        // Test SerialGenerator specifically
        $generator = $registry->getGenerator('serial');
        $this->assertInstanceOf(SerialGenerator::class, $generator);

        $types = $registry->getAvailableTypes();
        $this->assertContains('auto_increment', $types);
        $this->assertContains('uuid_v4', $types);
        $this->assertContains('uuid_v7', $types);
        $this->assertContains('ulid', $types);
        $this->assertContains('serial', $types);
    }

    public function testCustomStrategy(): void
    {
        $registry = new GeneratorRegistry();
        $strategy = new PrefixedIdGenerator();
        $registry->addStrategy($strategy);

        $foundStrategy = $registry->findStrategy('prefixed_id');
        $this->assertSame($strategy, $foundStrategy);

        $id = $strategy->generate('TestEntity', ['prefix' => 'TEST_', 'length' => 4]);
        $this->assertStringStartsWith('TEST_', $id);
        $this->assertEquals(9, strlen($id)); // TEST_ + 4 chars
    }

    public function testCustomGeneratorWithFallback(): void
    {
        $generator = new CustomGenerator();

        // Without any strategies, should use fallback (auto-increment style)
        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');
        $id3 = $generator->generate('OtherEntity');

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
        $this->assertEquals(1, $id3); // Different entity gets its own sequence
    }

    public function testCustomGeneratorWithStrategy(): void
    {
        $generator = new CustomGenerator();
        $strategy = new PrefixedIdGenerator('PRE_', 3);
        $generator->addStrategy($strategy);

        // Should use the prefixed strategy
        $id = $generator->generate('TestEntity', ['generator' => 'prefixed_id']);
        $this->assertStringStartsWith('PRE_', $id);
        $this->assertEquals(7, strlen($id)); // PRE_ + 3 chars
    }

    public function testCustomGeneratorWithMultipleStrategies(): void
    {
        $generator = new CustomGenerator();

        $strategy1 = new TestStrategy('strategy1');
        $strategy2 = new TestStrategy('strategy2');

        $generator->addStrategy($strategy1);
        $generator->addStrategy($strategy2);

        // First matching strategy should be used
        $id = $generator->generate('TestEntity', ['generator' => 'strategy1']);
        $this->assertEquals('generated_by_strategy1', $id);
    }

    public function testCustomGeneratorFallbackWhenNoStrategyMatches(): void
    {
        $generator = new CustomGenerator();
        $strategy = new TestStrategy('specific_type');
        $generator->addStrategy($strategy);

        // Request a different type than what the strategy supports
        // The fallback uses a static counter, so we can't predict the exact value
        // but it should be an integer >= 1
        $id = $generator->generate('TestEntity', ['generator' => 'other_type']);
        $this->assertIsInt($id);
        $this->assertGreaterThanOrEqual(1, $id);
    }

    public function testCustomGeneratorTypeName(): void
    {
        $generator = new CustomGenerator();
        $this->assertEquals('custom', $generator->getType());
    }
}

// Test SerialGenerator specifically
class SerialGeneratorTest extends \PHPUnit\Framework\TestCase
{
    public function testSerialGeneratorTypeName(): void
    {
        $generator = new SerialGenerator();
        $this->assertEquals('serial', $generator->getType());
    }

    public function testSerialGeneratorGeneratesSequentialIds(): void
    {
        $generator = new SerialGenerator();

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');
        $id3 = $generator->generate('TestEntity');

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
        $this->assertEquals(3, $id3);
    }

    public function testSerialGeneratorMaintainsSeparateSequences(): void
    {
        $generator = new SerialGenerator();

        $userId1 = $generator->generate('User');
        $userId2 = $generator->generate('User');
        $postId1 = $generator->generate('Post');

        $this->assertEquals(1, $userId1);
        $this->assertEquals(2, $userId2);
        $this->assertEquals(1, $postId1); // Separate sequence for Post
    }

    public function testSerialGeneratorWithOptions(): void
    {
        $generator = new SerialGenerator();

        // Options are ignored for SerialGenerator (uses in-memory sequences)
        $id1 = $generator->generate('TestEntity', ['start' => 100]);
        $id2 = $generator->generate('TestEntity', ['increment' => 5]);

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
    }

    public function testSerialGeneratorReturnsIntegers(): void
    {
        $generator = new SerialGenerator();

        $id = $generator->generate('TestEntity');

        $this->assertIsInt($id);
    }
}

// Mock strategy for testing
class TestStrategy implements GeneratorStrategyInterface {
    public function __construct(private string $supportedType) {}

    public function generate(string $entityClass, array $options = []): mixed
    {
        return 'generated_by_' . $this->supportedType;
    }

    public function getName(): string
    {
        return $this->supportedType;
    }

    public function supports(string $generatorType): bool
    {
        return $generatorType === $this->supportedType;
    }
}
