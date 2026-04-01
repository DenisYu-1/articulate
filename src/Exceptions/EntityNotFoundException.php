<?php

namespace Articulate\Exceptions;

class EntityNotFoundException extends ArticulateException {
    public static function invalidClass(string $className): self
    {
        return new self("Entity class '{$className}' is not a valid entity");
    }

    public static function notFound(string $entityClass, mixed $id): self
    {
        return new self("Entity {$entityClass} with ID {$id} not found in database");
    }
}
