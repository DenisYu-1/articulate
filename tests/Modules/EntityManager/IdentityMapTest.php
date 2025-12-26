<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\IdentityMap;
use PHPUnit\Framework\TestCase;

class IdentityMapTest extends TestCase
{
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        $this->identityMap = new IdentityMap();
    }

    public function testAddAndGetEntity(): void
    {
        $entity = new class() {
            public int $id = 1;

            public string $name = 'Test Entity';
        };

        $this->identityMap->add($entity, 1);

        $retrieved = $this->identityMap->get($entity::class, 1);
        $this->assertSame($entity, $retrieved);
    }

    public function testHasEntity(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        $this->assertFalse($this->identityMap->has($entity::class, 1));

        $this->identityMap->add($entity, 1);

        $this->assertTrue($this->identityMap->has($entity::class, 1));
    }

    public function testRemoveEntity(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        $this->identityMap->add($entity, 1);
        $this->assertTrue($this->identityMap->has($entity::class, 1));

        $this->identityMap->remove($entity);
        $this->assertFalse($this->identityMap->has($entity::class, 1));
    }

    public function testClearSpecificClass(): void
    {
        $entity1 = new class() {
            public int $id = 1;
        };
        $entity2 = new class() {
            public int $id = 2;
        };

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 2);

        $this->identityMap->clear($entity1::class);

        $this->assertFalse($this->identityMap->has($entity1::class, 1));
        $this->assertTrue($this->identityMap->has($entity2::class, 2));
    }

    public function testClearAll(): void
    {
        $entity1 = new class() {
            public int $id = 1;
        };
        $entity2 = new class() {
            public int $id = 2;
        };

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 2);

        $this->identityMap->clear();

        $this->assertFalse($this->identityMap->has($entity1::class, 1));
        $this->assertFalse($this->identityMap->has($entity2::class, 2));
    }

    public function testGenerateKeyForSimpleId(): void
    {
        $this->assertEquals('123', $this->identityMap->generateKey(123));
        $this->assertEquals('abc', $this->identityMap->generateKey('abc'));
    }

    public function testGenerateKeyForCompositeId(): void
    {
        $compositeId = ['user_id' => 1, 'group_id' => 2];
        $key = $this->identityMap->generateKey($compositeId);

        $this->assertIsString($key);
        $this->assertNotEmpty($key);

        // Key should be consistent for same data
        $key2 = $this->identityMap->generateKey($compositeId);
        $this->assertEquals($key, $key2);
    }

    public function testCompositeKeyOrderConsistency(): void
    {
        $id1 = ['a' => 1, 'b' => 2];
        $id2 = ['b' => 2, 'a' => 1]; // Same data, different order

        $key1 = $this->identityMap->generateKey($id1);
        $key2 = $this->identityMap->generateKey($id2);

        $this->assertEquals($key1, $key2);
    }

    public function testDifferentClassesSameId(): void
    {
        $class1 = 'TestClass1';
        $class2 = 'TestClass2';

        $entity1 = new class() {
            public int $id = 1;
        };
        $entity2 = new class() {
            public int $id = 1;
        };

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 1);

        $this->assertSame($entity1, $this->identityMap->get($entity1::class, 1));
        $this->assertSame($entity2, $this->identityMap->get($entity2::class, 1));
        $this->assertNotSame($entity1, $entity2);
    }

    public function testGetNonExistentEntity(): void
    {
        $this->assertNull($this->identityMap->get('NonExistentClass', 1));
    }

    public function testRemoveNonExistentEntity(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        // Should not throw an exception
        $this->identityMap->remove($entity);
        $this->assertTrue(true);
    }
}
