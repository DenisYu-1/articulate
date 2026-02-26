<?php

namespace Articulate\Modules\EntityManager;

interface UpdateConflictResolutionStrategy {
    /**
     * @param array<int, array{entity: object, changes: array}|array{table: string, set: array<string, mixed>, where: string, whereValues: array<int, mixed>}> $updates
     * @return array<int, array{entity: object, changes: array}|array{table: string, set: array<string, mixed>, where: string, whereValues: array<int, mixed>}>
     */
    public function resolve(array $updates, EntityMetadataRegistry $metadataRegistry): array;
}
