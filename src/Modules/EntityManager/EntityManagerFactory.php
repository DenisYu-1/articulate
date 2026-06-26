<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;

class EntityManagerFactory {
    public static function create(
        Connection $connection,
        EntityManagerOptions $options = new EntityManagerOptions(),
    ): EntityManager {
        return new EntityManager(
            $connection,
            $options->changeTrackingStrategy,
            $options->hydrator,
            $options->generatorRegistry,
            $options->metadataRegistry,
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
