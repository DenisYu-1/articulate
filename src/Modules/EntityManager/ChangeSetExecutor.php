<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Schema\EntityMetadataRegistry;

class ChangeSetExecutor {
    public function __construct(
        private readonly QueryExecutor $queryExecutor,
        private readonly EntityMetadataRegistry $metadataRegistry,
        private readonly EntityDependencySorter $dependencySorter,
    ) {
    }

    /**
     * @param array{inserts: object[], updates: array<int, array{entity?: object, changes?: array, table?: string, set?: array, where?: string, whereValues?: array}>, deletes: object[], softDeletes: object[]} $changes
     */
    public function execute(array $changes): void
    {
        $orderedInserts = $this->dependencySorter->order($changes['inserts'], 'insert');
        foreach ($orderedInserts as $entity) {
            $this->queryExecutor->executeInsert($entity);
        }

        foreach ($orderedInserts as $entity) {
            $this->queryExecutor->syncManyToMany($entity);
        }

        $updatedEntities = [];
        foreach ($changes['updates'] as $update) {
            if (isset($update['table'])) {
                $this->queryExecutor->executeUpdateByTable(
                    tableName: $update['table'],
                    columnChanges: $update['set'],
                    whereClause: $update['where'],
                    whereValues: $update['whereValues'],
                );

                continue;
            }

            $this->queryExecutor->executeUpdate($update['entity'], $update['changes']);
            $updatedEntities[] = $update['entity'];
        }

        foreach ($updatedEntities as $entity) {
            $this->queryExecutor->syncManyToMany($entity);
        }

        $orderedDeletes = $this->dependencySorter->order($changes['deletes'], 'delete');
        foreach ($orderedDeletes as $entity) {
            $this->queryExecutor->deletePivotRows($entity);
            $this->queryExecutor->executeDelete($entity);
        }

        foreach ($changes['softDeletes'] as $entity) {
            $this->executeSoftDelete($entity);
        }
    }

    /**
     * @param UnitOfWork[] $unitOfWorks
     */
    public function syncManagedManyToMany(array $unitOfWorks): void
    {
        foreach ($unitOfWorks as $unitOfWork) {
            foreach ($unitOfWork->getManagedEntities() as $entity) {
                $this->queryExecutor->syncManyToMany($entity, dirtyOnly: true);
            }
        }
    }

    private function executeSoftDelete(object $entity): void
    {
        $metadata = $this->metadataRegistry->getMetadata($entity::class);
        $softDeleteColumn = $metadata->getSoftDeleteColumn();

        if ($softDeleteColumn === null) {
            return;
        }

        $where = $this->queryExecutor->buildEntityWhereClause($entity);

        $this->queryExecutor->executeUpdateByTable(
            $metadata->getTableName(),
            [$softDeleteColumn => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            $where['clause'],
            $where['values'],
        );
    }
}
