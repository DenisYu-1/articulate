<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Entity]
class RelBehaviourTarget {
    #[PrimaryKey]
    #[Property]
    public string $id;

    #[Property]
    public string $name;
}

#[Entity]
class RelBehaviourTargetWithoutPk {
    #[Property]
    public string $name;
}

#[Entity]
class RelBehaviourOwner {
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToOne(onDelete: 'CASCADE')]
    public RelBehaviourTarget $inferredTarget;

    #[ManyToOne(targetEntity: RelBehaviourTargetWithoutPk::class)]
    public ?RelBehaviourTargetWithoutPk $pkLessTarget;

    #[ManyToOne(targetEntity: RelBehaviourTarget::class, foreignKey: false)]
    public ?RelBehaviourTarget $withoutForeignKey;

    #[OneToOne(targetEntity: RelBehaviourTarget::class, referencedBy: 'owner')]
    public ?RelBehaviourTarget $inverseConfigured;

    #[OneToOne(targetEntity: RelBehaviourTarget::class, ownedBy: 'owner')]
    public ?RelBehaviourTarget $ownedByTarget;

    #[OneToOne(targetEntity: RelBehaviourTarget::class)]
    public ?RelBehaviourTarget $standaloneOneToOne;

    #[OneToMany(targetEntity: RelBehaviourTarget::class, ownedBy: 'owner')]
    public array $children;

    #[OneToMany(targetEntity: RelBehaviourTarget::class, ownedBy: 'owner')]
    public string $brokenCollection;

    #[MorphTo(typeColumn: 'subject_kind', idColumn: 'subject_ref')]
    public ?object $subject;

    #[MorphOne(targetEntity: RelBehaviourTarget::class, morphType: 'custom-morph', typeColumn: 'one_kind', idColumn: 'one_ref')]
    public ?RelBehaviourTarget $morphOne;

    #[MorphMany(targetEntity: RelBehaviourTarget::class, typeColumn: 'many_kind', idColumn: 'many_ref')]
    public iterable $morphMany;
}

#[Entity]
class RelBehaviourConflicting {
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[OneToOne(targetEntity: RelBehaviourTarget::class, ownedBy: 'owner', referencedBy: 'other')]
    public ?RelBehaviourTarget $conflicting;
}

class ReflectionRelationBehaviourTest extends TestCase {
    public function testGetOnDeleteReturnsConfiguredValueAndNullForMorphTo(): void
    {
        $this->assertSame('CASCADE', $this->relation('inferredTarget')->getOnDelete());
        $this->assertNull($this->relation('withoutForeignKey')->getOnDelete());
        $this->assertNull($this->relation('subject')->getOnDelete());
    }

    public function testTargetEntityIsInferredFromPropertyTypeWithoutCollectionCheck(): void
    {
        $relation = $this->relation('inferredTarget');

        $this->assertSame(RelBehaviourTarget::class, $relation->getTargetEntity());
        $this->assertSame('string', $relation->getType());
    }

    public function testGetTypeFallsBackToIntWhenTargetHasNoPrimaryKey(): void
    {
        $this->assertSame('int', $this->relation('pkLessTarget')->getType());
    }

    public function testIsForeignKeyRequiredDependsOnRelationKind(): void
    {
        $this->assertTrue($this->relation('inferredTarget')->isForeignKeyRequired());
        $this->assertFalse($this->relation('withoutForeignKey')->isForeignKeyRequired());
        $this->assertFalse($this->relation('children')->isForeignKeyRequired());
        $this->assertFalse($this->relation('ownedByTarget')->isForeignKeyRequired());
        $this->assertTrue($this->relation('inverseConfigured')->isForeignKeyRequired());
    }

    public function testIsNullableFollowsPropertyTypeAndExplicitOverride(): void
    {
        $this->assertFalse($this->relation('inferredTarget')->isNullable());
        $this->assertTrue($this->relation('withoutForeignKey')->isNullable());
    }

    public function testGetInversedByUsesConfiguredValueOrDerivesFromDeclaringClass(): void
    {
        $this->assertSame('owner', $this->relation('inverseConfigured')->getInversedBy());
        $this->assertSame('rel_behaviour_owner_id', $this->relation('ownedByTarget')->getInversedBy());
    }

    public function testGetInversedByReturnsNullForStandaloneOwningOneToOne(): void
    {
        $this->assertNull($this->relation('standaloneOneToOne')->getInversedBy());
    }

    public function testGetInversedByRejectsBothSidesConfigured(): void
    {
        $relation = $this->relationOf(RelBehaviourConflicting::class, 'conflicting');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ownedBy and referencedBy cannot be specified at the same time');
        $relation->getInversedBy();
    }

    public function testGetMappedByDerivesColumnWhenOnlyInverseSideConfigured(): void
    {
        $this->assertSame('owner', $this->relation('ownedByTarget')->getMappedBy());
        $this->assertSame('inverse_configured_id', $this->relation('inverseConfigured')->getMappedBy());
    }

    public function testOneToManyRejectsNonCollectionPropertyType(): void
    {
        $relation = $this->relation('brokenCollection');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-many property must be iterable collection');
        $relation->getTargetEntity();
    }

    public function testMorphColumnAccessorsForEveryMorphAttribute(): void
    {
        $this->assertSame('subject_kind', $this->relation('subject')->getMorphTypeColumnName());
        $this->assertSame('subject_ref', $this->relation('subject')->getMorphIdColumnName());

        $this->assertSame('one_kind', $this->relation('morphOne')->getMorphTypeColumnName());
        $this->assertSame('one_ref', $this->relation('morphOne')->getMorphIdColumnName());

        $this->assertSame('many_kind', $this->relation('morphMany')->getMorphTypeColumnName());
        $this->assertSame('many_ref', $this->relation('morphMany')->getMorphIdColumnName());
    }

    public function testGetMorphTypeOnlyAvailableForOwningMorphRelations(): void
    {
        $this->assertSame('custom-morph', $this->relation('morphOne')->getMorphType());
        $this->assertSame(RelBehaviourTarget::class, $this->relation('morphMany')->getMorphType());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not an owning polymorphic relation');
        $this->relation('subject')->getMorphType();
    }

    public function testMorphColumnAccessorsRejectNonPolymorphicRelations(): void
    {
        $relation = $this->relation('inferredTarget');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not a polymorphic relation');
        $relation->getMorphIdColumnName();
    }

    private function relation(string $propertyName): ReflectionRelation
    {
        return $this->relationOf(RelBehaviourOwner::class, $propertyName);
    }

    private function relationOf(string $entityClass, string $propertyName): ReflectionRelation
    {
        foreach (new ReflectionEntity($entityClass)->getEntityRelationProperties() as $relation) {
            if ($relation instanceof ReflectionRelation && $relation->getPropertyName() === $propertyName) {
                return $relation;
            }
        }

        $this->fail("Relation '{$propertyName}' not found on {$entityClass}");
    }
}
