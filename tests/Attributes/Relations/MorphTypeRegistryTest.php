<?php

namespace Articulate\Tests\Attributes\Relations;

use Articulate\Attributes\Relations\MorphTypeRegistry;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSecondEntity;
use PHPUnit\Framework\TestCase;

class MorphTypeRegistryTest extends TestCase {
    protected function setUp(): void
    {
        MorphTypeRegistry::clear();
    }

    protected function tearDown(): void
    {
        MorphTypeRegistry::clear();
    }

    public function testRegisterAndGetAlias(): void
    {
        MorphTypeRegistry::register(TestEntity::class, 'test');

        $this->assertSame('test', MorphTypeRegistry::getAlias(TestEntity::class));
        $this->assertSame(TestEntity::class, MorphTypeRegistry::getEntityClass('test'));
    }

    public function testGetAliasReturnsClassNameWhenNotRegistered(): void
    {
        $this->assertSame(TestEntity::class, MorphTypeRegistry::getAlias(TestEntity::class));
    }

    public function testGetEntityClassReturnsAliasWhenNotRegistered(): void
    {
        $this->assertSame('unknown_alias', MorphTypeRegistry::getEntityClass('unknown_alias'));
    }

    public function testHasAlias(): void
    {
        $this->assertFalse(MorphTypeRegistry::hasAlias('test'));

        MorphTypeRegistry::register(TestEntity::class, 'test');

        $this->assertTrue(MorphTypeRegistry::hasAlias('test'));
    }

    public function testClear(): void
    {
        MorphTypeRegistry::register(TestEntity::class, 'test');
        MorphTypeRegistry::register(TestSecondEntity::class, 'second');

        MorphTypeRegistry::clear();

        $this->assertEmpty(MorphTypeRegistry::getMappings());
        $this->assertFalse(MorphTypeRegistry::hasAlias('test'));
        $this->assertFalse(MorphTypeRegistry::hasAlias('second'));
    }

    public function testRegisterCleansUpOldMappings(): void
    {
        MorphTypeRegistry::register(TestEntity::class, 'old_alias');
        MorphTypeRegistry::register(TestEntity::class, 'new_alias');

        $this->assertSame('new_alias', MorphTypeRegistry::getAlias(TestEntity::class));
        $this->assertFalse(MorphTypeRegistry::hasAlias('old_alias'));
        $this->assertTrue(MorphTypeRegistry::hasAlias('new_alias'));

        MorphTypeRegistry::register(TestSecondEntity::class, 'shared');
        MorphTypeRegistry::register(TestEntity::class, 'shared');

        $this->assertSame('shared', MorphTypeRegistry::getAlias(TestEntity::class));
        $this->assertNotSame('shared', MorphTypeRegistry::getAlias(TestSecondEntity::class));
    }

    public function testRegisterThrowsForNonExistentClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MorphTypeRegistry::register('NonExistent\\Class\\Name', 'alias');
    }
}
