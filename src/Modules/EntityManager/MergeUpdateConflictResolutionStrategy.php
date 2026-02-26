<?php

namespace Articulate\Modules\EntityManager;

class MergeUpdateConflictResolutionStrategy implements UpdateConflictResolutionStrategy {
    public function resolve(array $updates, EntityMetadataRegistry $metadataRegistry): array
    {
        $result = [];
        $combinedIndexes = [];

        foreach ($updates as $update) {
            if (isset($update['table'])) {
                $result[] = $update;

                continue;
            }

            $entity = $update['entity'];
            $metadata = $metadataRegistry->getMetadata($entity::class);

            if (!$this->canCombineUpdate($metadata)) {
                $result[] = $update;

                continue;
            }

            $identity = $this->buildUpdateIdentity($entity, $metadata);
            if ($identity === null) {
                $result[] = $update;

                continue;
            }

            [$whereClause, $whereValues, $identityKey] = $identity;
            $columnChanges = $this->mapChangesToColumns($update['changes'], $metadata);

            if ($columnChanges === []) {
                $result[] = $update;

                continue;
            }

            $tableName = $metadata->getTableName();
            $groupKey = $tableName . '|' . $identityKey;

            if (!isset($combinedIndexes[$groupKey])) {
                $combinedIndexes[$groupKey] = count($result);
                $result[] = [
                    'table' => $tableName,
                    'set' => $columnChanges,
                    'where' => $whereClause,
                    'whereValues' => $whereValues,
                ];

                continue;
            }

            $index = $combinedIndexes[$groupKey];
            $result[$index]['set'] = array_merge($result[$index]['set'], $columnChanges);
        }

        return $result;
    }

    private function canCombineUpdate(EntityMetadata $metadata): bool
    {
        foreach ($metadata->getRelations() as $relation) {
            if ($relation->isMorphTo()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{string, array<int, mixed>, string}|null
     */
    private function buildUpdateIdentity(object $entity, EntityMetadata $metadata): ?array
    {
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();

        if (!empty($primaryKeyColumns)) {
            $identityParts = [];
            $whereParts = [];
            $whereValues = [];

            foreach ($primaryKeyColumns as $columnName) {
                $propertyName = $metadata->getPropertyNameForColumn($columnName);
                if ($propertyName === null) {
                    return null;
                }

                $property = $metadata->getProperty($propertyName);
                if ($property === null) {
                    return null;
                }

                $value = $property->getValue($entity);
                if ($value === null) {
                    return null;
                }

                $identityParts[] = $columnName . '=' . $value;
                $whereParts[] = "{$columnName} = ?";
                $whereValues[] = $value;
            }

            return [implode(' AND ', $whereParts), $whereValues, implode('|', $identityParts)];
        }

        $idProperty = $metadata->getProperty('id');
        if ($idProperty === null) {
            return null;
        }

        $idValue = $idProperty->getValue($entity);
        if ($idValue === null) {
            return null;
        }

        $columnName = $idProperty->getColumnName();
        $whereClause = "{$columnName} = ?";

        return [$whereClause, [$idValue], $columnName . '=' . $idValue];
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function mapChangesToColumns(array $changes, EntityMetadata $metadata): array
    {
        $columnChanges = [];

        foreach ($changes as $propertyName => $value) {
            $columnName = $metadata->getColumnName($propertyName);
            if ($columnName === null) {
                continue;
            }

            $columnChanges[$columnName] = $value;
        }

        return $columnChanges;
    }
}
