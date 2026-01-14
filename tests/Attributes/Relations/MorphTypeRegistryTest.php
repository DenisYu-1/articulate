<?php

namespace Articulate\Tests\Attributes\Relations;

use Articulate\Attributes\Relations\MorphTypeRegistry;
use Articulate\Tests\AbstractTestCase;

class MorphTypeRegistryTest extends AbstractTestCase {
    protected function setUp(): void
    {
        parent::setUp();
        // Clear registry before each test to ensure clean state
        MorphTypeRegistry::clear();
    }

    protected function tearDown(): void
    {
        // Clear registry after each test
        MorphTypeRegistry::clear();
        parent::tearDown();
    }

    public function testRegisterValidEntityClass(): void
    {
        MorphTypeRegistry::register(self::class, 'test_alias');

        $this->assertEquals('test_alias', MorphTypeRegistry::getAlias(self::class));
        $this->assertEquals(self::class, MorphTypeRegistry::getEntityClass('test_alias'));
        $this->assertTrue(MorphTypeRegistry::hasAlias('test_alias'));
    }

    public function testRegisterNonExistentEntityClassThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Entity class 'NonExistentClass' does not exist");

        MorphTypeRegistry::register('NonExistentClass', 'alias');
    }

    public function testGetAliasReturnsClassNameWhenNotRegistered(): void
    {
        $className = 'Some\Namespace\UnregisteredClass';

        $this->assertEquals($className, MorphTypeRegistry::getAlias($className));
    }

    public function testGetEntityClassReturnsAliasWhenNotRegistered(): void
    {
        $alias = 'unregistered_alias';

        $this->assertEquals($alias, MorphTypeRegistry::getEntityClass($alias));
    }

    public function testHasAliasReturnsFalseForUnregisteredAlias(): void
    {
        $this->assertFalse(MorphTypeRegistry::hasAlias('nonexistent_alias'));
    }

    public function testHasAliasReturnsTrueForRegisteredAlias(): void
    {
        MorphTypeRegistry::register(self::class, 'registered_alias');

        $this->assertTrue(MorphTypeRegistry::hasAlias('registered_alias'));
    }

    public function testGetMappingsReturnsAllRegisteredMappings(): void
    {
        MorphTypeRegistry::register(self::class, 'alias1');
        MorphTypeRegistry::register(\stdClass::class, 'alias2');

        $mappings = MorphTypeRegistry::getMappings();

        $this->assertCount(2, $mappings);
        $this->assertArrayHasKey(self::class, $mappings);
        $this->assertArrayHasKey(\stdClass::class, $mappings);
        $this->assertEquals('alias1', $mappings[self::class]);
        $this->assertEquals('alias2', $mappings[\stdClass::class]);
    }

    public function testClearRemovesAllMappings(): void
    {
        MorphTypeRegistry::register(self::class, 'alias1');
        MorphTypeRegistry::register(\stdClass::class, 'alias2');

        // Verify mappings exist
        $this->assertEquals('alias1', MorphTypeRegistry::getAlias(self::class));
        $this->assertEquals('alias2', MorphTypeRegistry::getAlias(\stdClass::class));

        MorphTypeRegistry::clear();

        // Verify mappings are cleared
        $this->assertEquals(self::class, MorphTypeRegistry::getAlias(self::class));
        $this->assertEquals(\stdClass::class, MorphTypeRegistry::getAlias(\stdClass::class));
        $this->assertEmpty(MorphTypeRegistry::getMappings());
        $this->assertFalse(MorphTypeRegistry::hasAlias('alias1'));
        $this->assertFalse(MorphTypeRegistry::hasAlias('alias2'));
    }

    public function testMultipleRegistrationsWorkCorrectly(): void
    {
        MorphTypeRegistry::register(self::class, 'alias1');
        MorphTypeRegistry::register(\stdClass::class, 'alias2');
        MorphTypeRegistry::register(\Exception::class, 'alias3');

        $this->assertEquals('alias1', MorphTypeRegistry::getAlias(self::class));
        $this->assertEquals('alias2', MorphTypeRegistry::getAlias(\stdClass::class));
        $this->assertEquals('alias3', MorphTypeRegistry::getAlias(\Exception::class));

        $this->assertEquals(self::class, MorphTypeRegistry::getEntityClass('alias1'));
        $this->assertEquals(\stdClass::class, MorphTypeRegistry::getEntityClass('alias2'));
        $this->assertEquals(\Exception::class, MorphTypeRegistry::getEntityClass('alias3'));
    }

    public function testRegisteringSameEntityTwiceUpdatesAlias(): void
    {
        MorphTypeRegistry::register(self::class, 'old_alias');
        $this->assertEquals('old_alias', MorphTypeRegistry::getAlias(self::class));

        MorphTypeRegistry::register(self::class, 'new_alias');
        $this->assertEquals('new_alias', MorphTypeRegistry::getAlias(self::class));
    }

    public function testRegisteringSameAliasForDifferentEntities(): void
    {
        MorphTypeRegistry::register(self::class, 'same_alias');
        $this->assertEquals(self::class, MorphTypeRegistry::getEntityClass('same_alias'));

        MorphTypeRegistry::register(\stdClass::class, 'same_alias');
        // The alias should now point to the last registered class
        $this->assertEquals(\stdClass::class, MorphTypeRegistry::getEntityClass('same_alias'));
    }
}
