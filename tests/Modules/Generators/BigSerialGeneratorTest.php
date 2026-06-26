<?php

namespace Articulate\Tests\Modules\Generators;

use Articulate\Modules\Generators\BigSerialGenerator;
use PHPUnit\Framework\TestCase;

class BigSerialGeneratorTest extends TestCase {
    public function testGetType(): void
    {
        $generator = new BigSerialGenerator();

        $this->assertEquals('bigserial', $generator->getType());
    }

    public function testGenerate(): void
    {
        $generator = new BigSerialGenerator();

        $this->assertNull($generator->generate('TestEntity'));
        $this->assertNull($generator->generate('TestEntity'));
        $this->assertNull($generator->generate('OtherEntity'));
    }
}
