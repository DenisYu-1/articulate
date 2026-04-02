<?php

namespace Articulate\Tests\Modules\Generators;

use Articulate\Modules\Generators\PrefixedIdGeneratorAdapter;
use PHPUnit\Framework\TestCase;

class PrefixedIdGeneratorAdapterTest extends TestCase {
    public function testGetType(): void
    {
        $adapter = new PrefixedIdGeneratorAdapter();

        $this->assertEquals('prefixed_id', $adapter->getType());
    }

    public function testGenerateWithPrefix(): void
    {
        $adapter = new PrefixedIdGeneratorAdapter();

        $id = $adapter->generate('TestEntity', ['prefix' => 'USR_', 'length' => 6]);

        $this->assertStringStartsWith('USR_', $id);
        $this->assertEquals(10, strlen($id));
    }

    public function testGenerateDefaultPrefix(): void
    {
        $adapter = new PrefixedIdGeneratorAdapter();

        $id = $adapter->generate('TestEntity');

        $this->assertIsString($id);
        $this->assertEquals(8, strlen($id));
    }
}
