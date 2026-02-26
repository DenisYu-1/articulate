<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\Comparators\EntityTableComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\MappingTableComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;

class SchemaComparisonCoordinator {
    public function __construct(
        private readonly DatabaseSchemaReaderInterface $databaseSchemaReader,
        private readonly RelationDefinitionCollector $relationDefinitionCollector,
        private readonly EntityTableComparator $entityTableComparator,
        private readonly MappingTableComparator $mappingTableComparator,
    ) {
    }

    /**
     * @param ReflectionEntity[] $entities
     * @return iterable<TableCompareResult>
     */
    public function compareAll(array $entities): iterable
    {
        $existingTables = $this->databaseSchemaReader->getTables();
        $tablesToRemove = array_fill_keys($existingTables, true);

        $this->relationDefinitionCollector->validateRelations($entities);
        $manyToManyTables = $this->relationDefinitionCollector->collectManyToManyTables($entities);
        $morphToManyTables = $this->relationDefinitionCollector->collectMorphToManyTables($entities);

        $entitiesIndexed = $this->indexByTableName($entities);
        foreach ($entitiesIndexed as $tableName => $entityGroup) {
            unset($tablesToRemove[$tableName]);

            $compareResult = $this->entityTableComparator->compareEntityTable($entityGroup, $existingTables, $tableName);
            if ($compareResult !== null) {
                yield $compareResult;
            }
        }

        foreach ($manyToManyTables as $definition) {
            unset($tablesToRemove[$definition['tableName']]);
            $compareResult = $this->mappingTableComparator->compareManyToManyTable($definition, $existingTables);
            if ($compareResult !== null) {
                yield $compareResult;
            }
        }

        foreach ($morphToManyTables as $definition) {
            unset($tablesToRemove[$definition['tableName']]);
            $compareResult = $this->mappingTableComparator->compareMorphToManyTable($definition, $existingTables);
            if ($compareResult !== null) {
                yield $compareResult;
            }
        }

        foreach (array_keys($tablesToRemove) as $tableName) {
            yield new TableCompareResult(
                $tableName,
                TableCompareResult::OPERATION_DELETE,
            );
        }
    }

    /**
     * @return array<string, ReflectionEntity[]>
     */
    private function indexByTableName(array $entities): array
    {
        $entitiesIndexed = [];
        foreach ($entities as $entity) {
            if (!$entity->isEntity()) {
                continue;
            }
            $tableName = $entity->getTableName();
            if (!isset($entitiesIndexed[$tableName])) {
                $entitiesIndexed[$tableName] = [];
            }
            $entitiesIndexed[$tableName][] = $entity;
        }

        return $entitiesIndexed;
    }
}
