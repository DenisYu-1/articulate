<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Modules\Database\SchemaComparator\Comparators\EntityTableComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\MappingTableComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use PHPUnit\Framework\TestCase;

class SchemaComparisonCoordinatorTest extends TestCase {
    private DatabaseSchemaReaderInterface $schemaReader;

    private RelationDefinitionCollector $relationDefinitionCollector;

    private EntityTableComparator $entityTableComparator;

    private MappingTableComparator $mappingTableComparator;

    private SchemaComparisonCoordinator $coordinator;

    protected function setUp(): void
    {
        $this->schemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $this->relationDefinitionCollector = $this->createMock(RelationDefinitionCollector::class);
        $this->entityTableComparator = $this->createMock(EntityTableComparator::class);
        $this->mappingTableComparator = $this->createMock(MappingTableComparator::class);

        $this->coordinator = new SchemaComparisonCoordinator(
            $this->schemaReader,
            $this->relationDefinitionCollector,
            $this->entityTableComparator,
            $this->mappingTableComparator,
        );
    }

    public function testSchemaComparisonCoordinatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SchemaComparisonCoordinator::class, $this->coordinator);
    }

    public function testCompareAllWithEmptyEntitiesReturnsEmptyIterable(): void
    {
        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([])
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([])
            ->willReturn([]);

        $result = $this->coordinator->compareAll([]);

        $this->assertIsIterable($result);
        $results = iterator_to_array($result);
        $this->assertEmpty($results);
    }

    public function testCompareAllWithEntitiesThatCreateTables(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $entity->method('isEntity')->willReturn(true);
        $entity->method('getTableName')->willReturn('users');

        $createResult = new TableCompareResult('users', TableCompareResult::OPERATION_CREATE);

        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([$entity]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->entityTableComparator->expects($this->once())
            ->method('compareEntityTable')
            ->with([$entity], [], 'users')
            ->willReturn($createResult);

        $result = $this->coordinator->compareAll([$entity]);

        $results = iterator_to_array($result);
        $this->assertCount(1, $results);
        $this->assertSame($createResult, $results[0]);
    }

    public function testCompareAllWithExistingTablesToDelete(): void
    {
        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn(['old_table', 'another_table']);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([])
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([])
            ->willReturn([]);

        $result = $this->coordinator->compareAll([]);

        $results = iterator_to_array($result);
        $this->assertCount(2, $results);

        $tableNames = array_map(fn ($result) => $result->name, $results);
        $this->assertContains('old_table', $tableNames);
        $this->assertContains('another_table', $tableNames);

        foreach ($results as $result) {
            $this->assertEquals(TableCompareResult::OPERATION_DELETE, $result->operation);
        }
    }

    public function testCompareAllWithManyToManyRelations(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $entity->method('isEntity')->willReturn(true);
        $entity->method('getTableName')->willReturn('users');

        $manyToManyDefinition = [
            'tableName' => 'user_posts',
            'ownerTable' => 'users',
            'targetTable' => 'posts',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'post_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['user_id', 'post_id'],
        ];

        $mappingResult = new TableCompareResult('user_posts', TableCompareResult::OPERATION_CREATE);

        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([$entity]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([$entity])
            ->willReturn(['user_posts' => $manyToManyDefinition]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->entityTableComparator->expects($this->once())
            ->method('compareEntityTable')
            ->with([$entity], [], 'users')
            ->willReturn(null);

        $this->mappingTableComparator->expects($this->once())
            ->method('compareManyToManyTable')
            ->with($manyToManyDefinition, [])
            ->willReturn($mappingResult);

        $result = $this->coordinator->compareAll([$entity]);

        $results = iterator_to_array($result);
        $this->assertCount(1, $results);
        $this->assertSame($mappingResult, $results[0]);
    }

    public function testCompareAllWithMorphToManyRelations(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $entity->method('isEntity')->willReturn(true);
        $entity->method('getTableName')->willReturn('posts');

        $morphToManyDefinition = [
            'tableName' => 'taggables',
            'morphName' => 'taggable',
            'typeColumn' => 'taggable_type',
            'idColumn' => 'taggable_id',
            'targetColumn' => 'tag_id',
            'targetTable' => 'tags',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['taggable_type', 'taggable_id', 'tag_id'],
            'relations' => [$this->createMock(ReflectionMorphToMany::class)],
        ];

        $mappingResult = new TableCompareResult('taggables', TableCompareResult::OPERATION_CREATE);

        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([$entity]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([$entity])
            ->willReturn(['taggables' => $morphToManyDefinition]);

        $this->entityTableComparator->expects($this->once())
            ->method('compareEntityTable')
            ->with([$entity], [], 'posts')
            ->willReturn(null);

        $this->mappingTableComparator->expects($this->once())
            ->method('compareMorphToManyTable')
            ->with($morphToManyDefinition, [])
            ->willReturn($mappingResult);

        $result = $this->coordinator->compareAll([$entity]);

        $results = iterator_to_array($result);
        $this->assertCount(1, $results);
        $this->assertSame($mappingResult, $results[0]);
    }

    public function testCompareAllGroupsEntitiesByTableName(): void
    {
        $entity1 = $this->createMock(ReflectionEntity::class);
        $entity1->method('isEntity')->willReturn(true);
        $entity1->method('getTableName')->willReturn('users');

        $entity2 = $this->createMock(ReflectionEntity::class);
        $entity2->method('isEntity')->willReturn(true);
        $entity2->method('getTableName')->willReturn('users');

        $createResult = new TableCompareResult('users', TableCompareResult::OPERATION_CREATE);

        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([$entity1, $entity2]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([$entity1, $entity2])
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([$entity1, $entity2])
            ->willReturn([]);

        $this->entityTableComparator->expects($this->once())
            ->method('compareEntityTable')
            ->with([$entity1, $entity2], [], 'users')
            ->willReturn($createResult);

        $result = $this->coordinator->compareAll([$entity1, $entity2]);

        $results = iterator_to_array($result);
        $this->assertCount(1, $results);
        $this->assertSame($createResult, $results[0]);
    }

    public function testCompareAllFiltersOutNonEntityClasses(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $entity->method('isEntity')->willReturn(false); // Not an entity

        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([$entity]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->entityTableComparator->expects($this->never())
            ->method('compareEntityTable');

        $result = $this->coordinator->compareAll([$entity]);

        $results = iterator_to_array($result);
        $this->assertEmpty($results);
    }

    public function testCompareAllHandlesMixedOperations(): void
    {
        $entity = $this->createMock(ReflectionEntity::class);
        $entity->method('isEntity')->willReturn(true);
        $entity->method('getTableName')->willReturn('users');

        $manyToManyDefinition = [
            'tableName' => 'user_posts',
            'ownerTable' => 'users',
            'targetTable' => 'posts',
            'ownerJoinColumn' => 'user_id',
            'targetJoinColumn' => 'post_id',
            'ownerReferencedColumn' => 'id',
            'targetReferencedColumn' => 'id',
            'extraProperties' => [],
            'primaryColumns' => ['user_id', 'post_id'],
        ];

        $entityResult = new TableCompareResult('users', TableCompareResult::OPERATION_CREATE);
        $mappingResult = new TableCompareResult('user_posts', TableCompareResult::OPERATION_CREATE);
        $deleteResult = new TableCompareResult('old_table', TableCompareResult::OPERATION_DELETE);

        $this->schemaReader->expects($this->once())
            ->method('getTables')
            ->willReturn(['old_table']);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('validateRelations')
            ->with([$entity]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectManyToManyTables')
            ->with([$entity])
            ->willReturn(['user_posts' => $manyToManyDefinition]);

        $this->relationDefinitionCollector->expects($this->once())
            ->method('collectMorphToManyTables')
            ->with([$entity])
            ->willReturn([]);

        $this->entityTableComparator->expects($this->once())
            ->method('compareEntityTable')
            ->with([$entity], ['old_table'], 'users')
            ->willReturn($entityResult);

        $this->mappingTableComparator->expects($this->once())
            ->method('compareManyToManyTable')
            ->with($manyToManyDefinition, ['old_table'])
            ->willReturn($mappingResult);

        $result = $this->coordinator->compareAll([$entity]);

        $results = iterator_to_array($result);
        $this->assertCount(3, $results);

        $operations = array_map(fn ($result) => $result->operation, $results);
        $this->assertContains(TableCompareResult::OPERATION_CREATE, $operations);
        $this->assertContains(TableCompareResult::OPERATION_DELETE, $operations);
    }
}
