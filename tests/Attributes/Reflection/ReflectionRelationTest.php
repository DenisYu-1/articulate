<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneTarget;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMorphOneEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMorphToEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReflectionRelationTest extends TestCase
{
    /**
     * @return ReflectionRelation[]
     */
    private function getRelationsForEntity(string $entityClass): array
    {
        $entity = new ReflectionEntity($entityClass);

        return iterator_to_array($entity->getEntityRelationProperties());
    }

    private function findRelationByPropertyName(string $entityClass, string $propertyName): ReflectionRelation
    {
        foreach ($this->getRelationsForEntity($entityClass) as $relation) {
            if ($relation instanceof ReflectionRelation && $relation->getPropertyName() === $propertyName) {
                return $relation;
            }
        }

        $this->fail("Relation property '{$propertyName}' not found on {$entityClass}");
    }

    public static function relationTypeProvider(): iterable
    {
        yield 'ManyToOne is detected' => [TestManyToOneOwner::class, 'target', 'int'];
        yield 'OneToOne is detected' => [TestRelatedMainEntity::class, 'name', 'int'];
        yield 'MorphTo defaults to int' => [TestMorphToEntity::class, 'pollable', 'int'];
    }

    #[DataProvider('relationTypeProvider')]
    public function testGetType(string $entityClass, string $propertyName, string $expectedType): void
    {
        $relation = $this->findRelationByPropertyName($entityClass, $propertyName);

        $this->assertSame($expectedType, $relation->getType());
    }

    public static function targetEntityProvider(): iterable
    {
        yield 'ManyToOne resolves target' => [TestManyToOneOwner::class, 'target', TestManyToOneTarget::class];
        yield 'OneToOne resolves target' => [TestRelatedMainEntity::class, 'name', TestRelatedEntity::class];
        yield 'MorphTo returns null' => [TestMorphToEntity::class, 'pollable', null];
    }

    #[DataProvider('targetEntityProvider')]
    public function testGetTargetEntity(string $entityClass, string $propertyName, ?string $expectedTarget): void
    {
        $relation = $this->findRelationByPropertyName($entityClass, $propertyName);

        $this->assertSame($expectedTarget, $relation->getTargetEntity());
    }

    public static function owningSideProvider(): iterable
    {
        yield 'ManyToOne is always owning' => [TestManyToOneOwner::class, 'target', true];
        yield 'OneToOne without ownedBy is owning' => [TestRelatedMainEntity::class, 'name', true];
        yield 'OneToOne with ownedBy is not owning' => [TestRelatedEntity::class, 'name', false];
        yield 'OneToMany is not owning' => [TestManyToOneTarget::class, 'owners', false];
        yield 'MorphTo is owning' => [TestMorphToEntity::class, 'pollable', true];
        yield 'MorphOne is not owning' => [TestMorphOneEntity::class, 'morphToEntity', false];
    }

    #[DataProvider('owningSideProvider')]
    public function testIsOwningSide(string $entityClass, string $propertyName, bool $expected): void
    {
        $relation = $this->findRelationByPropertyName($entityClass, $propertyName);

        $this->assertSame($expected, $relation->isOwningSide());
    }

    public function testGetMappedBy(): void
    {
        $relation = $this->findRelationByPropertyName(TestRelatedEntity::class, 'name');

        $this->assertSame('name', $relation->getMappedByProperty());
    }

    public function testGetMappedByReturnsNullWhenNotSet(): void
    {
        $relation = $this->findRelationByPropertyName(TestManyToOneOwner::class, 'target');

        $this->assertNull($relation->getMappedByProperty());
    }

    public static function propertyNameProvider(): iterable
    {
        yield 'ManyToOne property name' => [TestManyToOneOwner::class, 'target'];
        yield 'OneToOne property name' => [TestRelatedMainEntity::class, 'name'];
        yield 'MorphTo property name' => [TestMorphToEntity::class, 'pollable'];
    }

    #[DataProvider('propertyNameProvider')]
    public function testGetPropertyName(string $entityClass, string $expectedPropertyName): void
    {
        $relation = $this->findRelationByPropertyName($entityClass, $expectedPropertyName);

        $this->assertSame($expectedPropertyName, $relation->getPropertyName());
    }
}
