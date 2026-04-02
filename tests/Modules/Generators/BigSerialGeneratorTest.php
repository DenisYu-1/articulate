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

        $id1 = $generator->generate('TestEntity');
        $id2 = $generator->generate('TestEntity');
        $id3 = $generator->generate('OtherEntity');

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
        $this->assertEquals(1, $id3);
    }
}
