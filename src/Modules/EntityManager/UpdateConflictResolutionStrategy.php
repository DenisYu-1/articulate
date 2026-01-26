<?php

namespace Articulate\Modules\EntityManager;

interface UpdateConflictResolutionStrategy {
    /**
     * @param array{entity: object, changes: array}[] $updates
     * @return array{entity: object, changes: array}[]
     */
    public function resolve(array $updates, EntityMetadataRegistry $metadataRegistry): array;
}
