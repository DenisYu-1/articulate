<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Schema\EntityMetadataRegistry;

class EntityDependencySorter {
    public function __construct(
        private readonly EntityMetadataRegistry $metadataRegistry,
    ) {
    }

    /**
     * Order entities by their foreign key dependencies.
     *
     * For inserts: parents before children.
     * For deletes: children before parents.
     *
     * @param object[] $entities
     * @return object[]
     */
    public function order(array $entities, string $operation): array
    {
        if (empty($entities)) {
            return $entities;
        }

        return $this->topologicalSort($entities, $this->buildDependencyGraph($entities, $operation));
    }

    /**
     * @param object[] $entities
     * @return array<string, string[]>
     */
    public function buildDependencyGraph(array $entities, string $operation): array
    {
        $graph = [];

        $entitiesByClass = [];
        foreach ($entities as $entity) {
            $entitiesByClass[$entity::class][] = $entity;
        }

        foreach (array_keys($entitiesByClass) as $entityClass) {
            $graph[$entityClass] = [];
        }

        foreach ($entities as $entity) {
            $entityClass = $entity::class;
            $metadata = $this->metadataRegistry->getMetadata($entityClass);

            foreach ($metadata->getColumnRelations() as $relation) {
                $targetClass = $relation->getTargetEntity();

                if ($targetClass === null || !isset($entitiesByClass[$targetClass])) {
                    continue;
                }

                if ($operation === 'insert' && $relation->isForeignKeyRequired()) {
                    $graph[$entityClass][] = $targetClass;
                } elseif ($operation === 'delete' && $relation->isForeignKeyRequired()) {
                    $graph[$targetClass][] = $entityClass;
                }
            }
        }

        foreach ($graph as $class => $dependencies) {
            $graph[$class] = array_values(array_unique($dependencies));
        }

        return $graph;
    }

    /**
     * @param object[] $entities
     * @param array<string, string[]> $graph
     * @return object[]
     */
    public function topologicalSort(array $entities, array $graph): array
    {
        $result = [];
        $visited = [];
        $visiting = [];

        $entitiesByClass = [];
        foreach ($entities as $entity) {
            $entitiesByClass[$entity::class][] = $entity;
        }

        $visit = function (string $class) use (&$visit, &$result, &$visited, &$visiting, $graph, $entitiesByClass): void {
            if (isset($visited[$class])) {
                return;
            }

            if (isset($visiting[$class])) {
                $chain = implode(' → ', array_keys(array_filter($visiting))) . ' → ' . $class;

                throw new \RuntimeException("Circular dependency detected: {$chain}. Check your entity FK relationships.");
            }

            $visiting[$class] = true;

            foreach ($graph[$class] ?? [] as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$class]);
            $visited[$class] = true;

            if (isset($entitiesByClass[$class])) {
                $result = array_merge($result, $entitiesByClass[$class]);
            }
        };

        foreach (array_keys($entitiesByClass) as $class) {
            $visit($class);
        }

        return $result;
    }
}
