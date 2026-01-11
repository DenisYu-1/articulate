<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Indexes\PrimaryKey;
use PHPUnit\Framework\TestCase;

class PrimaryKeyAttributeTest extends TestCase
{
    public function testPrimaryKeyAttributeDefaultConstructor(): void
    {
        $primaryKey = new PrimaryKey();

        $this->assertNull($primaryKey->generator);
        $this->assertNull($primaryKey->sequence);
        $this->assertNull($primaryKey->options);
    }

    public function testPrimaryKeyAttributeWithGenerator(): void
    {
        $primaryKey = new PrimaryKey(generator: 'uuid_v4');

        $this->assertEquals('uuid_v4', $primaryKey->generator);
        $this->assertNull($primaryKey->sequence);
        $this->assertNull($primaryKey->options);
    }

    public function testPrimaryKeyAttributeWithSequence(): void
    {
        $primaryKey = new PrimaryKey(sequence: 'user_seq');

        $this->assertNull($primaryKey->generator);
        $this->assertEquals('user_seq', $primaryKey->sequence);
        $this->assertNull($primaryKey->options);
    }

    public function testPrimaryKeyAttributeWithOptions(): void
    {
        $options = ['prefix' => 'USR_', 'length' => 8];
        $primaryKey = new PrimaryKey(options: $options);

        $this->assertNull($primaryKey->generator);
        $this->assertNull($primaryKey->sequence);
        $this->assertEquals($options, $primaryKey->options);
    }

    public function testPrimaryKeyAttributeWithAllParameters(): void
    {
        $options = ['start' => 1000, 'increment' => 1];
        $primaryKey = new PrimaryKey(
            generator: 'custom',
            sequence: 'my_sequence',
            options: $options
        );

        $this->assertEquals('custom', $primaryKey->generator);
        $this->assertEquals('my_sequence', $primaryKey->sequence);
        $this->assertEquals($options, $primaryKey->options);
    }

    public function testPrimaryKeyGeneratorConstants(): void
    {
        $this->assertEquals('auto_increment', PrimaryKey::GENERATOR_AUTO_INCREMENT);
        $this->assertEquals('uuid_v4', PrimaryKey::GENERATOR_UUID_V4);
        $this->assertEquals('uuid_v7', PrimaryKey::GENERATOR_UUID_V7);
        $this->assertEquals('ulid', PrimaryKey::GENERATOR_ULID);
        $this->assertEquals('serial', PrimaryKey::GENERATOR_SERIAL);
        $this->assertEquals('bigserial', PrimaryKey::GENERATOR_BIGSERIAL);
    }

    public function testPrimaryKeyAttributeWithAutoIncrementGenerator(): void
    {
        $primaryKey = new PrimaryKey(generator: PrimaryKey::GENERATOR_AUTO_INCREMENT);

        $this->assertEquals('auto_increment', $primaryKey->generator);
    }

    public function testPrimaryKeyAttributeWithUuidGenerator(): void
    {
        $primaryKey = new PrimaryKey(generator: PrimaryKey::GENERATOR_UUID_V4);

        $this->assertEquals('uuid_v4', $primaryKey->generator);
    }

    public function testPrimaryKeyAttributeWithUlidGenerator(): void
    {
        $primaryKey = new PrimaryKey(generator: PrimaryKey::GENERATOR_ULID);

        $this->assertEquals('ulid', $primaryKey->generator);
    }

    public function testPrimaryKeyAttributeWithSerialGenerator(): void
    {
        $primaryKey = new PrimaryKey(generator: PrimaryKey::GENERATOR_SERIAL);

        $this->assertEquals('serial', $primaryKey->generator);
    }

    public function testPrimaryKeyAttributeWithBigSerialGenerator(): void
    {
        $primaryKey = new PrimaryKey(generator: PrimaryKey::GENERATOR_BIGSERIAL);

        $this->assertEquals('bigserial', $primaryKey->generator);
    }

    public function testPrimaryKeyAttributeWithEmptyOptionsArray(): void
    {
        $primaryKey = new PrimaryKey(options: []);

        $this->assertEquals([], $primaryKey->options);
    }

    public function testPrimaryKeyAttributePropertiesArePublic(): void
    {
        $primaryKey = new PrimaryKey('gen', 'seq', ['key' => 'value']);

        // Test modification
        $primaryKey->generator = 'new_gen';
        $primaryKey->sequence = 'new_seq';
        $primaryKey->options = ['new' => 'options'];

        $this->assertEquals('new_gen', $primaryKey->generator);
        $this->assertEquals('new_seq', $primaryKey->sequence);
        $this->assertEquals(['new' => 'options'], $primaryKey->options);
    }
}