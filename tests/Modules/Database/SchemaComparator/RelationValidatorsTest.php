<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\ManyToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\ManyToOneRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\MorphToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\OneToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\OneToOneRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\PolymorphicRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use PHPUnit\Framework\TestCase;

// Entity fixtures for validator guard-clause tests

#[Entity(tableName: 'rel_validator_posts')]
class RelValidatorPost {
    #[PrimaryKey]
    public int $id;

    /** @var array<int, RelValidatorComment> */
    #[OneToMany(targetEntity: RelValidatorComment::class, ownedBy: 'post')]
    public array $comments = [];
}

#[Entity(tableName: 'rel_validator_comments')]
class RelValidatorComment {
    #[PrimaryKey]
    public int $id;

    #[ManyToOne(targetEntity: RelValidatorPost::class)]
    public RelValidatorPost $post;
}

class RelationValidatorsTest extends TestCase {
    public function testManyToManyRelationValidatorCanBeInstantiated(): void
    {
        $validator = new ManyToManyRelationValidator();
        $this->assertInstanceOf(ManyToManyRelationValidator::class, $validator);
    }

    public function testManyToOneRelationValidatorCanBeInstantiated(): void
    {
        $validator = new ManyToOneRelationValidator();
        $this->assertInstanceOf(ManyToOneRelationValidator::class, $validator);
    }

    public function testMorphToManyRelationValidatorCanBeInstantiated(): void
    {
        $validator = new MorphToManyRelationValidator();
        $this->assertInstanceOf(MorphToManyRelationValidator::class, $validator);
    }

    public function testOneToManyRelationValidatorCanBeInstantiated(): void
    {
        $validator = new OneToManyRelationValidator();
        $this->assertInstanceOf(OneToManyRelationValidator::class, $validator);
    }

    public function testOneToOneRelationValidatorCanBeInstantiated(): void
    {
        $validator = new OneToOneRelationValidator();
        $this->assertInstanceOf(OneToOneRelationValidator::class, $validator);
    }

    public function testPolymorphicRelationValidatorCanBeInstantiated(): void
    {
        $validator = new PolymorphicRelationValidator();
        $this->assertInstanceOf(PolymorphicRelationValidator::class, $validator);
    }

    public function testRelationValidatorFactoryCanBeInstantiated(): void
    {
        $factory = new RelationValidatorFactory();
        $this->assertInstanceOf(RelationValidatorFactory::class, $factory);
    }

    // ── Guard-clause mutation killers ─────────────────────────────────────────

    public function testManyToOneValidatorReturnsEarlyForNonManyToOneRelation(): void
    {
        $entity = new ReflectionEntity(RelValidatorPost::class);
        $oneToManyRelation = null;
        foreach ($entity->getEntityRelationProperties() as $rel) {
            if ($rel instanceof ReflectionRelation && $rel->isOneToMany()) {
                $oneToManyRelation = $rel;
                break;
            }
        }
        $this->assertNotNull($oneToManyRelation, 'Test fixture must expose a OneToMany relation');

        $validator = new ManyToOneRelationValidator();
        $validator->validate($oneToManyRelation); // must not throw
        $this->assertSame(ManyToOneRelationValidator::class, $validator::class);
    }

    public function testOneToManyValidatorReturnsEarlyForNonOneToManyRelation(): void
    {
        $entity = new ReflectionEntity(RelValidatorComment::class);
        $manyToOneRelation = null;
        foreach ($entity->getEntityRelationProperties() as $rel) {
            if ($rel instanceof ReflectionRelation && $rel->isManyToOne()) {
                $manyToOneRelation = $rel;
                break;
            }
        }
        $this->assertNotNull($manyToOneRelation, 'Test fixture must expose a ManyToOne relation');

        $validator = new OneToManyRelationValidator();
        $validator->validate($manyToOneRelation); // must not throw
        $this->assertSame(OneToManyRelationValidator::class, $validator::class);
    }

    public function testPolymorphicValidatorReturnsEarlyForNonReflectionRelation(): void
    {
        $nonReflectionRelation = new class implements RelationInterface {
            public function getTargetEntity(): ?string { return null; }
            public function getDeclaringClassName(): string { return self::class; }
            public function isOwningSide(): bool { return true; }
            public function getMappedBy(): ?string { return null; }
            public function getInversedBy(): ?string { return null; }
            public function getPropertyName(): string { return 'stub'; }
            public function isLazy(): bool { return false; }
        };

        $validator = new PolymorphicRelationValidator();
        $validator->validate($nonReflectionRelation); // must not throw/error
        $this->assertSame(PolymorphicRelationValidator::class, $validator::class);
    }}
