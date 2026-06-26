<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\Comparators\EntityTableComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\MappingTableComparator;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use RuntimeException;

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
        $entitiesIndexed = $this->indexByTableName($entities);
        $this->validateIndexes($entitiesIndexed);

        $existingTables = $this->databaseSchemaReader->getTables();
        $tablesToRemove = array_fill_keys($existingTables, true);

        $this->relationDefinitionCollector->validateRelations($entities);

        $manyToManyTables = $this->relationDefinitionCollector->collectManyToManyTables($entities);
        $morphToManyTables = $this->relationDefinitionCollector->collectMorphToManyTables($entities);

        $results = [];
        foreach ($entitiesIndexed as $tableName => $entityGroup) {
            unset($tablesToRemove[$tableName]);

            $compareResult = $this->entityTableComparator->compareEntityTable($entityGroup, $existingTables, $tableName);
            if ($compareResult !== null) {
                $results[] = $compareResult;
            }
        }

        foreach ($manyToManyTables as $definition) {
            unset($tablesToRemove[$definition['tableName']]);
            $compareResult = $this->mappingTableComparator->compareManyToManyTable($definition, $existingTables);
            if ($compareResult !== null) {
                $results[] = $compareResult;
            }
        }

        foreach ($morphToManyTables as $definition) {
            unset($tablesToRemove[$definition['tableName']]);
            $compareResult = $this->mappingTableComparator->compareMorphToManyTable($definition, $existingTables);
            if ($compareResult !== null) {
                $results[] = $compareResult;
            }
        }

        foreach ($this->sortTablesForDeletion(array_keys($tablesToRemove)) as $tableName) {
            $results[] = new TableCompareResult(
                $tableName,
                TableCompareResult::OPERATION_DELETE,
            );
        }

        yield from $this->sortTableResults($results);
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

    /**
     * @param array<string, ReflectionEntity[]> $entitiesIndexed
     */
    private function validateIndexes(array $entitiesIndexed): void
    {
        foreach ($entitiesIndexed as $entityGroup) {
            foreach ($entityGroup as $entity) {
                foreach ($entity->getAttributes(Index::class) as $indexAttribute) {
                    /** @var Index $index */
                    $index = $indexAttribute->newInstance();
                    $index->resolveColumns($entity);
                }
            }
        }
    }

    /**
     * @param TableCompareResult[] $results
     * @return TableCompareResult[]
     */
    private function sortTableResults(array $results): array
    {
        $orderedResults = [];
        $dependencyCandidates = [];
        $deleteResults = [];

        foreach ($results as $result) {
            if ($result->operation === CompareResult::OPERATION_DELETE) {
                $deleteResults[] = $result;

                continue;
            }

            $dependencyCandidates[$result->name] = $result;
        }

        $visited = [];
        $visiting = [];
        $visit = function (TableCompareResult $result) use (&$visit, &$orderedResults, &$visited, &$visiting, $dependencyCandidates): void {
            if (isset($visited[$result->name])) {
                return;
            }

            if (isset($visiting[$result->name])) {
                $cycle = implode(' -> ', array_keys($visiting)) . ' -> ' . $result->name;

                throw new RuntimeException(
                    "Circular table foreign key dependency detected while ordering schema changes: {$cycle}. "
                    . 'Create one side of the relationship without an inline foreign key and add the constraint in a later migration.'
                );
            }

            $visiting[$result->name] = true;
            foreach ($this->getForeignKeyDependencies($result, array_keys($dependencyCandidates)) as $dependencyName) {
                $visit($dependencyCandidates[$dependencyName]);
            }
            unset($visiting[$result->name]);

            $visited[$result->name] = true;
            $orderedResults[] = $result;
        };

        foreach ($dependencyCandidates as $result) {
            $visit($result);
        }

        return array_merge($orderedResults, $deleteResults);
    }

    /**
     * @param string[] $knownTables
     * @return string[]
     */
    private function getForeignKeyDependencies(TableCompareResult $result, array $knownTables): array
    {
        $knownTables = array_fill_keys($knownTables, true);
        $dependencies = [];

        foreach ($result->foreignKeys as $foreignKey) {
            if ($foreignKey->operation !== CompareResult::OPERATION_CREATE) {
                continue;
            }
            if ($foreignKey->referencedTable === $result->name || !isset($knownTables[$foreignKey->referencedTable])) {
                continue;
            }

            $dependencies[] = $foreignKey->referencedTable;
        }

        return array_values(array_unique($dependencies));
    }

    /**
     * @param string[] $tableNames
     * @return string[]
     */
    private function sortTablesForDeletion(array $tableNames): array
    {
        $tablesToDelete = array_fill_keys($tableNames, true);
        $graph = [];

        foreach ($tableNames as $tableName) {
            $graph[$tableName] = [];
            foreach ($this->databaseSchemaReader->getTableForeignKeys($tableName) as $foreignKey) {
                $referencedTable = $foreignKey['referencedTable'] ?? null;
                if (is_string($referencedTable) && isset($tablesToDelete[$referencedTable]) && $referencedTable !== $tableName) {
                    $graph[$tableName][] = $referencedTable;
                }
            }
            $graph[$tableName] = array_values(array_unique($graph[$tableName]));
        }

        $ordered = [];
        $visited = [];
        $visit = function (string $tableName) use (&$visit, &$ordered, &$visited, $graph): void {
            if (isset($visited[$tableName])) {
                return;
            }

            $visited[$tableName] = true;
            foreach ($graph as $dependentTable => $dependencies) {
                if (in_array($tableName, $dependencies, true)) {
                    $visit($dependentTable);
                }
            }

            $ordered[] = $tableName;
        };

        foreach ($tableNames as $tableName) {
            $visit($tableName);
        }

        return array_values(array_unique($ordered));
    }
}
