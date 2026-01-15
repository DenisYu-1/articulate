<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Attributes\Entity;
use Articulate\Attributes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Modules\Database\SchemaComparator\Comparators\ForeignKeyComparator;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Schema\SchemaNaming;
use PHPUnit\Framework\TestCase;

class ForeignKeyComparatorTest extends TestCase {
    private ForeignKeyComparator $comparator;

    protected function setUp(): void
    {
        $schemaNaming = new SchemaNaming();
        $relationValidatorFactory = new RelationValidatorFactory();

        $this->comparator = new ForeignKeyComparator($schemaNaming, $relationValidatorFactory);
    }

    public function testForeignKeyComparatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ForeignKeyComparator::class, $this->comparator);
    }

    public function testCreateForeignKeysForNewColumnsReturnsEmptyArrayWhenNoRelations(): void
    {
        $propertiesIndexed = [
            'id' => [
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
            ],
        ];

        $createdColumnsWithForeignKeys = [];
        $tableName = 'users';

        $result = $this->comparator->createForeignKeysForNewColumns($propertiesIndexed, $createdColumnsWithForeignKeys, $tableName);

        $this->assertEmpty($result);
        $this->assertEmpty($createdColumnsWithForeignKeys);
    }

    public function testCreateForeignKeysForNewColumnsCreatesForeignKeyForRelationWithForeignKeyRequired(): void
    {
        // Mock the ManyToOne relation
        $manyToOneAttribute = new ManyToOne(TestEntity::class);
        $reflectionProperty = new \ReflectionProperty(TestEntity::class, 'id');
        $reflectionRelation = new ReflectionRelation($manyToOneAttribute, $reflectionProperty);

        $propertiesIndexed = [
            'user_id' => [
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => $reflectionRelation,
                'foreignKeyRequired' => true,
                'referencedColumn' => 'id',
            ],
        ];

        $createdColumnsWithForeignKeys = [];
        $tableName = 'posts';

        $result = $this->comparator->createForeignKeysForNewColumns($propertiesIndexed, $createdColumnsWithForeignKeys, $tableName);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ForeignKeyCompareResult::class, $result[0]);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result[0]->operation);
        $this->assertEquals('user_id', $result[0]->column);
        $this->assertEquals('test_entities', $result[0]->referencedTable);
        $this->assertEquals('id', $result[0]->referencedColumn);
        $this->assertEquals(['user_id' => true], $createdColumnsWithForeignKeys);
    }

    public function testCreateForeignKeysForNewColumnsSkipsRelationWithoutForeignKeyRequired(): void
    {
        // Mock the ManyToOne relation
        $manyToOneAttribute = new ManyToOne(TestEntity::class);
        $reflectionProperty = new \ReflectionProperty(TestEntity::class, 'id');
        $reflectionRelation = new ReflectionRelation($manyToOneAttribute, $reflectionProperty);

        $propertiesIndexed = [
            'user_id' => [
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => $reflectionRelation,
                'foreignKeyRequired' => false, // Not required
                'referencedColumn' => 'id',
            ],
        ];

        $createdColumnsWithForeignKeys = [];
        $tableName = 'posts';

        $result = $this->comparator->createForeignKeysForNewColumns($propertiesIndexed, $createdColumnsWithForeignKeys, $tableName);

        $this->assertEmpty($result);
        $this->assertEmpty($createdColumnsWithForeignKeys);
    }

    public function testCreateForeignKeysForNewColumnsHandlesMultipleColumns(): void
    {
        // Mock relations
        $manyToOneAttribute1 = new ManyToOne(TestEntity::class);
        $reflectionProperty1 = new \ReflectionProperty(TestEntity::class, 'id');
        $reflectionRelation1 = new ReflectionRelation($manyToOneAttribute1, $reflectionProperty1);

        $manyToOneAttribute2 = new ManyToOne(AnotherTestEntity::class);
        $reflectionProperty2 = new \ReflectionProperty(AnotherTestEntity::class, 'id');
        $reflectionRelation2 = new ReflectionRelation($manyToOneAttribute2, $reflectionProperty2);

        $propertiesIndexed = [
            'user_id' => [
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => $reflectionRelation1,
                'foreignKeyRequired' => true,
                'referencedColumn' => 'id',
            ],
            'category_id' => [
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => $reflectionRelation2,
                'foreignKeyRequired' => true,
                'referencedColumn' => 'id',
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
            ],
        ];

        $createdColumnsWithForeignKeys = [];
        $tableName = 'posts';

        $result = $this->comparator->createForeignKeysForNewColumns($propertiesIndexed, $createdColumnsWithForeignKeys, $tableName);

        $this->assertCount(2, $result);
        $this->assertEquals(['user_id' => true, 'category_id' => true], $createdColumnsWithForeignKeys);
    }
}

// Test entities for mocking
#[Entity(tableName: 'test_entities')]
class TestEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;
}

#[Entity(tableName: 'another_test_entities')]
class AnotherTestEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;
}
