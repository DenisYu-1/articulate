<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;
use Articulate\Schema\EntityMetadataRegistry;

class EntityManagerFactory {
    public static function create(
        Connection $connection,
        EntityManagerOptions $options = new EntityManagerOptions(),
    ): EntityManager {
        $metadataRegistry = $options->metadataRegistry ?? new EntityMetadataRegistry($options->metadataCache);

        return new EntityManager(
            $connection,
            $options->changeTrackingStrategy,
            $options->hydrator,
            $options->generatorRegistry,
            $metadataRegistry,
            $options->queryExecutor,
            $options->updateConflictResolutionStrategy,
            $options->resultCache,
            $options->repositoryFactory,
            $options->statementCache,
            $options->secondLevelCache,
            $options->secondLevelCacheTtl,
            $options->logger,
        );
    }
}
