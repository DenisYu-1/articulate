<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\IdentityMap;
use PHPUnit\Framework\TestCase;

class TestEntityForIdentityMap {
    public ?int $id = null;

    public string $name;
}

class IdentityMapTest extends TestCase {
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        $this->identityMap = new IdentityMap();
    }

    public function testAddAndGetEntity(): void
    {
        $entity = new TestEntityForIdentityMap();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $this->identityMap->add($entity, 1);

        $retrieved = $this->identityMap->get(TestEntityForIdentityMap::class, 1);

        $this->assertSame($entity, $retrieved);
        $this->assertTrue($this->identityMap->has(TestEntityForIdentityMap::class, 1));
    }

    public function testGetNonExistentEntity(): void
    {
        $result = $this->identityMap->get(TestEntityForIdentityMap::class, 999);

        $this->assertNull($result);
        $this->assertFalse($this->identityMap->has(TestEntityForIdentityMap::class, 999));
    }

    public function testHasReturnsFalseForNonExistentEntity(): void
    {
        $this->assertFalse($this->identityMap->has(TestEntityForIdentityMap::class, 1));
    }

    public function testAddMultipleEntitiesOfSameClass(): void
    {
        $entity1 = new TestEntityForIdentityMap();
        $entity1->id = 1;
        $entity1->name = 'Entity 1';

        $entity2 = new TestEntityForIdentityMap();
        $entity2->id = 2;
        $entity2->name = 'Entity 2';

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 2);

        $this->assertSame($entity1, $this->identityMap->get(TestEntityForIdentityMap::class, 1));
        $this->assertSame($entity2, $this->identityMap->get(TestEntityForIdentityMap::class, 2));
        $this->assertTrue($this->identityMap->has(TestEntityForIdentityMap::class, 1));
        $this->assertTrue($this->identityMap->has(TestEntityForIdentityMap::class, 2));
    }

    public function testAddMultipleEntitiesOfDifferentClasses(): void
    {
        $entity1 = new TestEntityForIdentityMap();
        $entity1->id = 1;

        $entity2 = new \stdClass();
        $entity2->id = 1;

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 1);

        $this->assertSame($entity1, $this->identityMap->get(TestEntityForIdentityMap::class, 1));
        $this->assertSame($entity2, $this->identityMap->get(\stdClass::class, 1));
    }

    public function testRemoveEntity(): void
    {
        $entity = new TestEntityForIdentityMap();
        $entity->id = 1;

        $this->identityMap->add($entity, 1);
        $this->assertTrue($this->identityMap->has(TestEntityForIdentityMap::class, 1));

        $this->identityMap->remove($entity);

        $this->assertFalse($this->identityMap->has(TestEntityForIdentityMap::class, 1));
        $this->assertNull($this->identityMap->get(TestEntityForIdentityMap::class, 1));
    }

    public function testRemoveNonExistentEntity(): void
    {
        $entity = new TestEntityForIdentityMap();
        $entity->id = 999;

        // Should not throw exception
        $this->identityMap->remove($entity);

        // Entity should still not exist
        $this->assertFalse($this->identityMap->has(TestEntityForIdentityMap::class, 999));
    }

    public function testClearAllEntities(): void
    {
        $entity1 = new TestEntityForIdentityMap();
        $entity1->id = 1;

        $entity2 = new \stdClass();
        $entity2->id = 1;

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 1);

        $this->identityMap->clear();

        $this->assertFalse($this->identityMap->has(TestEntityForIdentityMap::class, 1));
        $this->assertFalse($this->identityMap->has(\stdClass::class, 1));
    }

    public function testClearSpecificClass(): void
    {
        $entity1 = new TestEntityForIdentityMap();
        $entity1->id = 1;

        $entity2 = new \stdClass();
        $entity2->id = 1;

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 1);

        $this->identityMap->clear(TestEntityForIdentityMap::class);

        $this->assertFalse($this->identityMap->has(TestEntityForIdentityMap::class, 1));
        $this->assertTrue($this->identityMap->has(\stdClass::class, 1));
    }

    public function testClearNonExistentClass(): void
    {
        // Should not throw exception
        $this->identityMap->clear('NonExistentClass');

        // Map should remain empty
        $this->assertFalse($this->identityMap->has('NonExistentClass', 1));
    }

    public function testGenerateKeyWithStringId(): void
    {
        $this->assertEquals('123', $this->identityMap->generateKey('123'));
        $this->assertEquals('abc', $this->identityMap->generateKey('abc'));
    }

    public function testGenerateKeyWithIntegerId(): void
    {
        $this->assertEquals('123', $this->identityMap->generateKey(123));
        $this->assertEquals('0', $this->identityMap->generateKey(0));
    }

    public function testGenerateKeyWithCompositeKey(): void
    {
        $compositeId = ['user_id' => 1, 'post_id' => 2];

        $key = $this->identityMap->generateKey($compositeId);

        // Should be JSON encoded with sorted keys
        $expected = json_encode(['post_id' => 2, 'user_id' => 1]);
        $this->assertEquals($expected, $key);
    }

    public function testGenerateKeyWithDifferentOrderCompositeKey(): void
    {
        $compositeId1 = ['user_id' => 1, 'post_id' => 2];
        $compositeId2 = ['post_id' => 2, 'user_id' => 1];

        $key1 = $this->identityMap->generateKey($compositeId1);
        $key2 = $this->identityMap->generateKey($compositeId2);

        // Should generate the same key regardless of order
        $this->assertEquals($key1, $key2);
    }

    public function testGenerateKeyWithNullId(): void
    {
        $this->assertEquals('', $this->identityMap->generateKey(null));
    }

    public function testGenerateKeyWithBooleanId(): void
    {
        $this->assertEquals('1', $this->identityMap->generateKey(true));
        $this->assertEquals('', $this->identityMap->generateKey(false));
    }

    public function testAddWithCompositeKey(): void
    {
        $entity = new TestEntityForIdentityMap();
        $entity->id = 1;

        $compositeId = ['user_id' => 1, 'type' => 'admin'];

        $this->identityMap->add($entity, $compositeId);

        $this->assertTrue($this->identityMap->has(TestEntityForIdentityMap::class, $compositeId));
        $this->assertSame($entity, $this->identityMap->get(TestEntityForIdentityMap::class, $compositeId));
    }

    public function testOverwriteEntityWithSameId(): void
    {
        $entity1 = new TestEntityForIdentityMap();
        $entity1->id = 1;
        $entity1->name = 'Entity 1';

        $entity2 = new TestEntityForIdentityMap();
        $entity2->id = 1;
        $entity2->name = 'Entity 2';

        $this->identityMap->add($entity1, 1);
        $this->identityMap->add($entity2, 1); // Overwrite

        $retrieved = $this->identityMap->get(TestEntityForIdentityMap::class, 1);
        $this->assertSame($entity2, $retrieved);
    }
}
