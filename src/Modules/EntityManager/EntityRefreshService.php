<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;
use Articulate\Exceptions\EntityNotFoundException;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Schema\HydratorInterface;
use Articulate\Utils\ReflectionCache;
use Articulate\Utils\TypeRegistry;

class EntityRefreshService {
    public function __construct(
        private readonly Connection $connection,
        private HydratorInterface $hydrator,
        private readonly EntityMetadataRegistry $metadataRegistry,
        private readonly UnitOfWorkRegistry $unitOfWorkRegistry,
        private readonly EntityReadService $readService,
        private readonly TypeRegistry $typeRegistry,
    ) {
    }

    public function setHydrator(HydratorInterface $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    public function refresh(object $entity): void
    {
        $entityClass = $entity instanceof Proxy\ProxyInterface
            ? $entity->getProxyEntityClass()
            : $entity::class;

        $metadata = $this->metadataRegistry->getMetadata($entityClass);
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();

        if (empty($primaryKeyColumns)) {
            throw new \RuntimeException("Entity {$entityClass} has no primary key");
        }

        $primaryKeyColumn = $primaryKeyColumns[0];
        $propertyName = $metadata->getPropertyNameForColumn($primaryKeyColumn);

        if (!$propertyName) {
            throw new \RuntimeException("Cannot determine primary key property for entity {$entityClass}");
        }

        $reflectionProperty = ReflectionCache::getProperty($entity::class, $propertyName);
        $reflectionProperty->setAccessible(true);
        $id = $reflectionProperty->getValue($entity);

        if ($id === null) {
            throw new \RuntimeException("Entity {$entityClass} has null primary key value");
        }

        $qb = $this->readService->createQueryBuilder($entityClass)
            ->select(...$metadata->getColumnNames())
            ->from($metadata->getTableName())
            ->where($primaryKeyColumn, $id)
            ->limit(1);

        $statement = $this->connection->executeQuery($qb->getSQL(), $qb->getParameters());
        $rawResults = $statement->fetchAll();

        if (empty($rawResults)) {
            throw EntityNotFoundException::notFound($entityClass, $id);
        }

        $freshData = $rawResults[0];

        if ($entity instanceof Proxy\ProxyInterface && !$entity->isProxyInitialized()) {
            $this->hydrator->hydrate($entityClass, $freshData, $entity);
            $entity->markProxyInitialized();
        } else {
            $this->updateEntityProperties($entity, $freshData, $metadata);
        }

        $this->unitOfWorkRegistry->active()->registerManaged($entity, $freshData);
    }

    private function updateEntityProperties(object $entity, array $data, EntityMetadata $metadata): void
    {
        foreach ($metadata->getProperties() as $propertyName => $property) {
            $columnName = $property->getColumnName();
            if (!array_key_exists($columnName, $data)) {
                continue;
            }

            $converter = $this->typeRegistry->getConverter($property->getType());
            $phpValue = $converter ? $converter->convertToPHP($data[$columnName]) : $data[$columnName];

            $reflectionProperty = ReflectionCache::getProperty($entity::class, $propertyName);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($entity, $phpValue);
        }
    }
}
