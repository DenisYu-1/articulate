<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\ArrayHydrator;
use Articulate\Modules\EntityManager\HydratorInterface;
use PHPUnit\Framework\TestCase;

class ArrayHydratorTest extends TestCase {
    private ArrayHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ArrayHydrator();
    }

    public function testImplementsHydratorInterface(): void
    {
        $this->assertInstanceOf(HydratorInterface::class, $this->hydrator);
    }

    public function testHydrateReturnsArray(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
        ];

        $result = $this->hydrator->hydrate('SomeClass', $data);

        $this->assertSame($data, $result);
        $this->assertIsArray($result);
    }

    public function testHydrateWithExistingEntityReturnsArray(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $existingEntity = new \stdClass();

        $result = $this->hydrator->hydrate('SomeClass', $data, $existingEntity);

        $this->assertSame($data, $result);
        $this->assertNotSame($existingEntity, $result);
    }

    public function testExtractArray(): void
    {
        $array = ['id' => 1, 'name' => 'Test'];

        $result = $this->hydrator->extract($array);

        $this->assertSame($array, $result);
    }

    public function testExtractNonArrayThrowsException(): void
    {
        $entity = new \stdClass();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ArrayHydrator is for read-only operations');

        $this->hydrator->extract($entity);
    }

    public function testHydratePartialThrowsException(): void
    {
        $entity = new \stdClass();
        $data = ['name' => 'Test'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Partial hydration not supported for arrays');

        $this->hydrator->hydratePartial($entity, $data);
    }
}
