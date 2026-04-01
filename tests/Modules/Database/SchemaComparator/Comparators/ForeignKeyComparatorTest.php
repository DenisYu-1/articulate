<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Modules\Database\SchemaComparator\Comparators\ForeignKeyComparator;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorInterface;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use PHPUnit\Framework\TestCase;

class ForeignKeyComparatorTest extends TestCase {
    private ForeignKeyComparator $comparator;

    protected function setUp(): void
    {
        $validatorFactory = $this->createStub(RelationValidatorFactory::class);
        $validator = $this->createStub(RelationValidatorInterface::class);
        $validatorFactory->method('getValidator')->willReturn($validator);

        $this->comparator = new ForeignKeyComparator(new SchemaNaming(), $validatorFactory);
    }

    private function createRelationStub(): ReflectionRelation
    {
        $relation = $this->createStub(ReflectionRelation::class);
        $relation->method('getTargetEntity')->willReturn(TestEntity::class);
        $relation->method('getReferencedColumnName')->willReturn('id');

        return $relation;
    }

    private function buildPropertyData(
        ?ReflectionRelation $relation = null,
        bool $foreignKeyRequired = false,
    ): array {
        return [
            'type' => 'int',
            'nullable' => false,
            'default' => null,
            'length' => null,
            'relation' => $relation,
            'foreignKeyRequired' => $foreignKeyRequired,
            'referencedColumn' => $relation?->getReferencedColumnName() ?? 'id',
            'generatorType' => null,
            'sequence' => null,
            'isPrimaryKey' => false,
            'isAutoIncrement' => false,
        ];
    }

    public function testCreateForeignKey(): void
    {
        $relation = $this->createRelationStub();

        $propertiesIndexed = [
            'entity_id' => $this->buildPropertyData(relation: $relation, foreignKeyRequired: true),
        ];

        $foreignKeysToRemove = [];
        $results = $this->comparator->compareForeignKeys(
            propertiesIndexed: $propertiesIndexed,
            existingForeignKeys: [],
            foreignKeysToRemove: $foreignKeysToRemove,
            createdColumnsWithForeignKeys: [],
            tableName: 'source_table',
        );

        $this->assertCount(1, $results);
        $this->assertSame(CompareResult::OPERATION_CREATE, $results[0]->operation);
        $this->assertSame('entity_id', $results[0]->column);
        $this->assertSame('test_entity', $results[0]->referencedTable);
        $this->assertSame('id', $results[0]->referencedColumn);
    }

    public function testDeleteForeignKey(): void
    {
        $fkName = 'fk_source_table_test_entity_entity_id';
        $existingForeignKeys = [
            $fkName => [
                'column' => 'entity_id',
                'referencedTable' => 'test_entity',
                'referencedColumn' => 'id',
            ],
        ];
        $foreignKeysToRemove = [$fkName => true];

        $results = $this->comparator->compareForeignKeys(
            propertiesIndexed: [],
            existingForeignKeys: $existingForeignKeys,
            foreignKeysToRemove: $foreignKeysToRemove,
            createdColumnsWithForeignKeys: [],
            tableName: 'source_table',
        );

        $this->assertCount(1, $results);
        $this->assertSame(CompareResult::OPERATION_DELETE, $results[0]->operation);
        $this->assertSame('entity_id', $results[0]->column);
        $this->assertSame('test_entity', $results[0]->referencedTable);
    }

    public function testNoChangeWhenForeignKeyMatches(): void
    {
        $relation = $this->createRelationStub();
        $fkName = 'fk_source_table_test_entity_entity_id';

        $propertiesIndexed = [
            'entity_id' => $this->buildPropertyData(relation: $relation, foreignKeyRequired: true),
        ];

        $existingForeignKeys = [
            $fkName => [
                'column' => 'entity_id',
                'referencedTable' => 'test_entity',
                'referencedColumn' => 'id',
            ],
        ];
        $foreignKeysToRemove = [$fkName => true];

        $results = $this->comparator->compareForeignKeys(
            propertiesIndexed: $propertiesIndexed,
            existingForeignKeys: $existingForeignKeys,
            foreignKeysToRemove: $foreignKeysToRemove,
            createdColumnsWithForeignKeys: [],
            tableName: 'source_table',
        );

        $this->assertCount(0, $results);
        $this->assertArrayNotHasKey($fkName, $foreignKeysToRemove);
    }
}
