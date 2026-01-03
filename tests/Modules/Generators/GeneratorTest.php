<?php

namespace Articulate\Tests\Modules\Generators;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Modules\Generators\AutoIncrementGenerator;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Modules\Generators\SerialGenerator;
use Articulate\Modules\Generators\Strategies\PrefixedIdGenerator;
use Articulate\Modules\Generators\UlidGenerator;
use Articulate\Modules\Generators\UuidGenerator;
use Articulate\Modules\Generators\UuidV7Generator;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class UuidEntity {
    #[PrimaryKey(generator: 'uuid_v4')]
    #[Property]
    public ?string $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class UuidV7Entity {
    #[PrimaryKey(generator: 'uuid_v7')]
    #[Property]
    public ?string $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class UlidEntity {
    #[PrimaryKey(generator: 'ulid')]
    #[Property]
    public ?string $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class SerialEntity {
    #[PrimaryKey(generator: 'serial')]
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;
}

#[Entity]
class PrefixedEntity {
    #[PrimaryKey(generator: 'prefixed_id', options: ['prefix' => 'USR_', 'length' => 6])]
    #[Property]
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
}
