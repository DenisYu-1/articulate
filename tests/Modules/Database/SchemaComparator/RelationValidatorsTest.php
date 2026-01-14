<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Modules\Database\SchemaComparator\RelationValidators\ManyToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\ManyToOneRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\MorphToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\OneToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\OneToOneRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\PolymorphicRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use PHPUnit\Framework\TestCase;

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
}