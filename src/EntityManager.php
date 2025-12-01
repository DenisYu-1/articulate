<?php

namespace Norm;

use Norm\Attributes\Entity;
use ReflectionClass;

class EntityManager
{
    public function getEntityMetadata(string $className): ?array
    {
        $reflectionClass = new ReflectionClass($className);

        $attributes = $reflectionClass->getAttributes(Entity::class);

        if (empty($attributes)) {
            return null; // Not an entity
        }

        /** @var Entity $entityAttribute */
        $entityAttribute = $attributes[0]->newInstance();

        return [
            'className' => $className,
            'tableName' => $entityAttribute->tableName ?? $reflectionClass->getShortName(),
        ];
    }
}
