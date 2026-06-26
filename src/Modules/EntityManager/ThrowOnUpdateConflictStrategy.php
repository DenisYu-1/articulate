<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Exceptions\UpdateConflictException;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\EntityMetadataRegistry;

/**
 * Rejects flushes where two different entity classes map to the same DB row and both have pending updates.
 * Use this when bounded-context writes must never silently overwrite each other.
 *
 * Usage:
 *   $em->setUpdateConflictResolutionStrategy(new ThrowOnUpdateConflictStrategy());
 *   // Now flush() throws UpdateConflictException instead of merging conflicting updates.
 *
 * @see MergeUpdateConflictResolutionStrategy for the default "last write wins" strategy
 */
class ThrowOnUpdateConflictStrategy implements UpdateConflictResolutionStrategy {
    public function resolve(array $updates, EntityMetadataRegistry $metadataRegistry): array
    {
        $seenRows = [];

        foreach ($updates as $update) {
            $entity = $update['entity'];
            $metadata = $metadataRegistry->getMetadata($entity::class);
            $rowIdentity = $this->buildRowIdentity($entity, $metadata);
            if ($rowIdentity === null) {
                continue;
            }

            if (!isset($seenRows[$rowIdentity])) {
                $seenRows[$rowIdentity] = $entity::class;

                continue;
            }

            throw new UpdateConflictException(
                sprintf(
                    'Conflicting updates for table "%s" row "%s" between "%s" and "%s".',
                    $metadata->getTableName(),
                    $rowIdentity,
                    $seenRows[$rowIdentity],
                    $entity::class,
                ),
            );
        }

        return $updates;
    }

    private function buildRowIdentity(object $entity, EntityMetadata $metadata): ?string
    {
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();
        if (empty($primaryKeyColumns)) {
            return null;
        }

        $identityParts = [];
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
        }

        return implode('|', $identityParts);
    }
}
