<?php

namespace Articulate\Modules\Repository;

use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\Repository\Exceptions\RepositoryException;

/**
 * Factory for creating and caching repository instances.
 */
class RepositoryFactory {
    /** @var array<string, RepositoryInterface> */
    private array $repositoryCache = [];

    public function __construct(
        private EntityManager $entityManager
    ) {
    }

    /**
     * Get or create a repository for the given entity class.
     *
     * @throws RepositoryException
     */
    public function getRepository(string $entityClass): RepositoryInterface
    {
        // Return cached repository if available
        if (isset($this->repositoryCache[$entityClass])) {
            return $this->repositoryCache[$entityClass];
        }

        // Get the repository class from entity metadata
        $metadata = $this->entityManager->getMetadataRegistry()->getMetadata($entityClass);
        $repositoryClass = $metadata->getRepositoryClass() ?? EntityRepository::class;

        // Validate custom repository class
        if ($repositoryClass !== EntityRepository::class) {
            $this->validateRepositoryClass($repositoryClass);
        }

        // Create and cache the repository
        $repository = $this->createRepository($repositoryClass, $entityClass);
        $this->repositoryCache[$entityClass] = $repository;

        return $repository;
    }

    /**
     * Create a repository instance.
     *
     * @throws RepositoryException
     */
    private function createRepository(string $repositoryClass, string $entityClass): RepositoryInterface
    {
        try {
            return new $repositoryClass($this->entityManager, $entityClass);
        } catch (\Throwable $e) {
            throw new RepositoryException(
                "Failed to create repository '{$repositoryClass}' for entity '{$entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validate that a custom repository class is valid.
     *
     * @throws RepositoryException
     */
    private function validateRepositoryClass(string $repositoryClass): void
    {
        // Check if class exists
        if (!class_exists($repositoryClass)) {
            throw new RepositoryException("Repository class '{$repositoryClass}' does not exist");
        }

        // Check if class implements RepositoryInterface
        if (!is_subclass_of($repositoryClass, RepositoryInterface::class)) {
            throw new RepositoryException(
                "Repository class '{$repositoryClass}' must implement RepositoryInterface"
            );
        }

        // Check if class extends AbstractRepository (recommended)
        if (!is_subclass_of($repositoryClass, AbstractRepository::class)) {
            throw new RepositoryException(
                "Repository class '{$repositoryClass}' must extend AbstractRepository"
            );
        }
    }

    /**
     * Clear the repository cache.
     */
    public function clearCache(): void
    {
        $this->repositoryCache = [];
    }

    /**
     * Get all cached repositories.
     *
     * @return array<string, RepositoryInterface>
     */
    public function getCachedRepositories(): array
    {
        return $this->repositoryCache;
    }
}
