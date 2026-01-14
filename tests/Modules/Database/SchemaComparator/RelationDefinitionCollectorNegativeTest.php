<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RelationDefinitionCollectorNegativeTest extends TestCase {
    private RelationDefinitionCollector $collector;

    protected function setUp(): void
    {
        // Use the default validator factory
        $this->collector = new RelationDefinitionCollector(new RelationValidatorFactory());
    }

    private function createCollectorWithCustomValidator(RelationValidatorInterface $customValidator): RelationDefinitionCollector
    {
        // Create a custom validator factory that includes our custom validator
        $validatorFactory = new class($customValidator) extends RelationValidatorFactory {
            private RelationValidatorInterface $customValidator;

            public function __construct(RelationValidatorInterface $customValidator)
            {
                $this->customValidator = $customValidator;
                parent::__construct();
            }

            public function getValidator(RelationInterface $relation): RelationValidatorInterface
            {
                // Check our custom validator first
                if ($this->customValidator->supports($relation)) {
                    return $this->customValidator;
                }

                // Fall back to parent implementation
                return parent::getValidator($relation);
            }
        };

        return new RelationDefinitionCollector($validatorFactory);
    }

    public function testValidateRelationsThrowsExceptionWhenValidatorFails(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $relation = $this->createMock(ReflectionRelation::class);

        $entity->expects($this->once())
            ->method('getEntityRelationProperties')
            ->willReturn([$relation]);

        // Create a failing validator
        $failingValidator = $this->createMock(RelationValidatorInterface::class);
        $failingValidator->expects($this->once())
            ->method('supports')
            ->with($relation)
            ->willReturn(true);
        $failingValidator->expects($this->once())
            ->method('validate')
            ->with($relation)
            ->willThrowException(new RuntimeException('Validation failed'));

        $collector = $this->createCollectorWithCustomValidator($failingValidator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed');

        $collector->validateRelations([$entity]);
    }

    public function testCollectManyToManyTablesThrowsOnConflictingJoinColumns(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);

        $relation1 = $this->createMock(ReflectionManyToMany::class);
        $relation2 = $this->createMock(ReflectionManyToMany::class);

        // Both relations claim to be for the same table but have different join columns
        // Set up both relations with conflicting join columns
        $relation1->method('isOwningSide')->willReturn(true);
        $relation1->method('getTableName')->willReturn('conflicting_table');
        $relation1->method('getDeclaringClassName')->willReturn('Articulate\\Connection');
        $relation1->method('getTargetEntity')->willReturn('Articulate\\Collection\\Collection');
        $relation1->method('getOwnerJoinColumn')->willReturn('user_id');
        $relation1->method('getTargetJoinColumn')->willReturn('post_id');
        $relation1->method('getOwnerPrimaryColumn')->willReturn('id');
        $relation1->method('getTargetPrimaryColumn')->willReturn('id');
        $relation1->method('getExtraProperties')->willReturn([]);
        $relation1->method('getPrimaryColumns')->willReturn(['user_id', 'post_id']);

        $relation2->method('isOwningSide')->willReturn(true);
        $relation2->method('getTableName')->willReturn('conflicting_table');
        $relation2->method('getDeclaringClassName')->willReturn('Articulate\\Collection\\Collection');
        $relation2->method('getTargetEntity')->willReturn('Articulate\\Connection');
        $relation2->method('getOwnerJoinColumn')->willReturn('different_user_id'); // Different column name
        $relation2->method('getTargetJoinColumn')->willReturn('post_id');

        $entity->expects($this->once())
            ->method('getEntityRelationProperties')
            ->willReturn([$relation1, $relation2]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many misconfigured: conflicting mapping table definition');

        $this->collector->collectManyToManyTables([$entity]);
    }

    public function testCollectMorphToManyTablesThrowsOnConflictingMorphNames(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);

        $relation1 = $this->createMock(ReflectionMorphToMany::class);
        $relation2 = $this->createMock(ReflectionMorphToMany::class);

        // Both relations for same table but different morph names
        $relation1->method('getTableName')->willReturn('taggables');
        $relation1->method('getTargetEntity')->willReturn('Articulate\\Connection');
        $relation1->method('getMorphName')->willReturn('taggable');
        $relation1->method('getTypeColumn')->willReturn('taggable_type');
        $relation1->method('getOwnerJoinColumn')->willReturn('taggable_id');
        $relation1->method('getTargetJoinColumn')->willReturn('tag_id');
        $relation1->method('getTargetPrimaryColumn')->willReturn('id');
        $relation1->method('getExtraProperties')->willReturn([]);
        $relation1->method('getPrimaryColumns')->willReturn(['taggable_type', 'taggable_id', 'tag_id']);

        $relation2->method('getTableName')->willReturn('taggables');
        $relation2->method('getTargetEntity')->willReturn('Articulate\\Connection');
        $relation2->method('getMorphName')->willReturn('different_morph'); // Different morph name

        $entity->expects($this->once())
            ->method('getEntityRelationProperties')
            ->willReturn([$relation1, $relation2]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Morph-to-many misconfigured: conflicting morph names for table 'taggables'");

        $this->collector->collectMorphToManyTables([$entity]);
    }

    public function testCollectManyToManyTablesWithConflictingExtraProperties(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);

        $relation1 = $this->createMock(ReflectionManyToMany::class);
        $relation2 = $this->createMock(ReflectionManyToMany::class);

        // Both relations for same table with conflicting extra properties
        $relation1->method('isOwningSide')->willReturn(true);
        $relation1->method('getTableName')->willReturn('user_posts');
        $relation1->method('getOwnerJoinColumn')->willReturn('user_id');
        $relation1->method('getTargetJoinColumn')->willReturn('post_id');
        $relation1->method('getOwnerPrimaryColumn')->willReturn('id');
        $relation1->method('getTargetPrimaryColumn')->willReturn('id');
        $relation1->method('getPrimaryColumns')->willReturn(['user_id', 'post_id']);

        $relation2->method('isOwningSide')->willReturn(true);
        $relation2->method('getTableName')->willReturn('user_posts');
        $relation2->method('getOwnerJoinColumn')->willReturn('user_id');
        $relation2->method('getTargetJoinColumn')->willReturn('post_id');
        $relation2->method('getOwnerPrimaryColumn')->willReturn('id');
        $relation2->method('getTargetPrimaryColumn')->willReturn('id');
        $relation2->method('getPrimaryColumns')->willReturn(['user_id', 'post_id']);

        $relation1->expects($this->once())
            ->method('getDeclaringClassName')
            ->willReturn('Articulate\\Connection');

        $relation1->expects($this->once())
            ->method('getTargetEntity')
            ->willReturn('Articulate\\Collection\\Collection');

        $relation2->expects($this->once())
            ->method('getDeclaringClassName')
            ->willReturn('Articulate\\Collection\\Collection');

        $relation2->expects($this->once())
            ->method('getTargetEntity')
            ->willReturn('Articulate\\Connection');

        // Conflicting extra properties - same name but different types
        $property1 = new MappingTableProperty('created_at', 'datetime', false, null, null);
        $property2 = new MappingTableProperty('created_at', 'timestamp', false, null, null); // Different type

        $relation1->expects($this->once())
            ->method('getExtraProperties')
            ->willReturn([$property1]);

        $relation2->expects($this->once())
            ->method('getExtraProperties')
            ->willReturn([$property2]);

        $entity->expects($this->once())
            ->method('getEntityRelationProperties')
            ->willReturn([$relation1, $relation2]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many misconfigured: mapping table "user_posts" property "created_at" conflicts between relations');

        $this->collector->collectManyToManyTables([$entity]);
    }

    public function testCollectMorphToManyTablesWithConflictingExtraProperties(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);

        $relation1 = $this->createMock(ReflectionMorphToMany::class);
        $relation2 = $this->createMock(ReflectionMorphToMany::class);

        // Both relations for same table with conflicting extra properties
        $relation1->method('getTableName')->willReturn('taggables');
        $relation1->method('getTargetEntity')->willReturn('Articulate\\Utils\\TypeRegistry');
        $relation1->method('getMorphName')->willReturn('taggable');
        $relation1->method('getTypeColumn')->willReturn('taggable_type');
        $relation1->method('getOwnerJoinColumn')->willReturn('taggable_id');
        $relation1->method('getTargetJoinColumn')->willReturn('tag_id');
        $relation1->method('getTargetPrimaryColumn')->willReturn('id');
        $relation1->method('getPrimaryColumns')->willReturn(['taggable_type', 'taggable_id', 'tag_id']);

        $relation2->method('getTableName')->willReturn('taggables');
        $relation2->method('getTargetEntity')->willReturn('Articulate\\Utils\\TypeRegistry');
        $relation2->method('getMorphName')->willReturn('taggable');
        $relation2->method('getTypeColumn')->willReturn('taggable_type');
        $relation2->method('getOwnerJoinColumn')->willReturn('taggable_id');
        $relation2->method('getTargetJoinColumn')->willReturn('tag_id');
        $relation2->method('getTargetPrimaryColumn')->willReturn('id');
        $relation2->method('getPrimaryColumns')->willReturn(['taggable_type', 'taggable_id', 'tag_id']);

        // Conflicting extra properties - same name but different lengths
        $property1 = new MappingTableProperty('metadata', 'text', false, null, null);
        $property2 = new MappingTableProperty('metadata', 'varchar', false, 100, null); // Different type

        $relation1->expects($this->once())
            ->method('getExtraProperties')
            ->willReturn([$property1]);

        $relation2->expects($this->once())
            ->method('getExtraProperties')
            ->willReturn([$property2]);

        $entity->expects($this->once())
            ->method('getEntityRelationProperties')
            ->willReturn([$relation1, $relation2]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many misconfigured: mapping table "taggables" property "metadata" conflicts between relations');

        $this->collector->collectMorphToManyTables([$entity]);
    }

    public function testValidationErrorsArePropagated(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $relation = $this->createMock(ReflectionRelation::class);

        $entity->expects($this->once())
            ->method('getEntityRelationProperties')
            ->willReturn([$relation]);

        // Create a failing validator
        $failingValidator = $this->createMock(RelationValidatorInterface::class);
        $failingValidator->expects($this->once())
            ->method('supports')
            ->with($relation)
            ->willReturn(true);
        $failingValidator->expects($this->once())
            ->method('validate')
            ->with($relation)
            ->willThrowException(new RuntimeException('Validation failed'));

        $collector = $this->createCollectorWithCustomValidator($failingValidator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed');

        $collector->validateRelations([$entity]);
    }
}
