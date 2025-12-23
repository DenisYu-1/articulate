<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\HydratorInterface;
use PHPUnit\Framework\TestCase;

class HydratorInterfaceTest extends TestCase
{
    public function testHydratorInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(HydratorInterface::class));
    }

    public function testHydratorInterfaceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(HydratorInterface::class);

        $this->assertTrue($reflection->hasMethod('hydrate'));
        $this->assertTrue($reflection->hasMethod('extract'));
        $this->assertTrue($reflection->hasMethod('hydratePartial'));

        // Check method signatures
        $hydrateMethod = $reflection->getMethod('hydrate');
        $this->assertEquals(3, $hydrateMethod->getNumberOfParameters());
        $this->assertEquals('mixed', $hydrateMethod->getReturnType()->getName());

        $extractMethod = $reflection->getMethod('extract');
        $this->assertEquals(1, $extractMethod->getNumberOfParameters());
        $this->assertEquals('array', $extractMethod->getReturnType()->getName());

        $hydratePartialMethod = $reflection->getMethod('hydratePartial');
        $this->assertEquals(2, $hydratePartialMethod->getNumberOfParameters());
        $this->assertEquals('void', $hydratePartialMethod->getReturnType()->getName());
    }
}
