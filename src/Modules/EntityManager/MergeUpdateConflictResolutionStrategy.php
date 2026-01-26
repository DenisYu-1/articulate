<?php

namespace Articulate\Modules\EntityManager;

class MergeUpdateConflictResolutionStrategy implements UpdateConflictResolutionStrategy {
    public function resolve(array $updates, EntityMetadataRegistry $metadataRegistry): array
    {
        return $updates;
    }
}
