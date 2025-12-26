<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\ScalarHydrator;
use PHPUnit\Framework\TestCase;

class ScalarHydratorTest extends TestCase
{
    private ScalarHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ScalarHydrator();
    }

    public function testImplementsHydratorInterface(): void
    {
        $this->assertInstanceOf(\Articulate\Modules\EntityManager\HydratorInterface::class, $this->hydrator);
    }

    public function testHydrateSingleColumnReturnsScalar(): void
    {
        $data = ['count' => 42];
        $result = $this->hydrator->hydrate('SomeClass', $data);

        $this->assertEquals(42, $result);
        $this->assertIsInt($result);
    }

    public function testHydrateMultipleColumnsThrowsException(): void
    {
        $data = ['count' => 42, 'name' => 'Test'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ScalarHydrator expects exactly one column in result');

        $this->hydrator->hydrate('SomeClass', $data);
    }

    public function testHydrateEmptyArrayThrowsException(): void
    {
        $data = [];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ScalarHydrator expects exactly one column in result');

        $this->hydrator->hydrate('SomeClass', $data);
    }

    public function testExtractThrowsException(): void
    {
        $entity = new \stdClass();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ScalarHydrator is for read-only operations');

        $this->hydrator->extract($entity);
    }

    public function testHydratePartialThrowsException(): void
    {
        $entity = new \stdClass();
        $data = ['value' => 123];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Partial hydration not supported for scalars');

        $this->hydrator->hydratePartial($entity, $data);
    }

    public function testHydrateDifferentScalarTypes(): void
    {
        $testCases = [
            [['count' => 42], 42, 'int'],
            [['name' => 'test'], 'test', 'string'],
            [['price' => 19.99], 19.99, 'float'],
            [['active' => true], true, 'bool'],
        ];

        foreach ($testCases as [$data, $expected, $type]) {
            $result = $this->hydrator->hydrate('SomeClass', $data);
            $this->assertEquals($expected, $result, "Failed for $type");

            // Type-specific assertions
            switch ($type) {
                case 'int':
                    $this->assertIsInt($result);

                    break;
                case 'string':
                    $this->assertIsString($result);

                    break;
                case 'float':
                    $this->assertIsFloat($result);

                    break;
                case 'bool':
                    $this->assertIsBool($result);

                    break;
            }
        }
    }
}
