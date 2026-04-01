<?php

namespace Articulate\Modules\EntityManager;

interface ChangeTrackingStrategy {
    public function trackEntity(object $entity, array $originalData): void;

    public function untrackEntity(object $entity): void;

    public function computeChangeSet(object $entity): array;

    public function refreshSnapshot(object $entity): void;

    public function clear(): void;
}
