<?php

namespace Articulate\Attributes\Relations;

use Articulate\Utils\StringUtils;

trait PolymorphicColumnResolution {
    private ?string $resolvedTypeColumn = null;

    private ?string $resolvedIdColumn = null;

    public function getTypeColumn(): string
    {
        return $this->resolvedTypeColumn ?? $this->typeColumn ?? '__UNRESOLVED_TYPE__';
    }

    public function getIdColumn(): string
    {
        return $this->resolvedIdColumn ?? $this->idColumn ?? '__UNRESOLVED_ID__';
    }

    public function resolveColumnNames(string $propertyName): void
    {
        $this->resolvedTypeColumn = $this->typeColumn ?? StringUtils::snakeCase($propertyName) . '_type';
        $this->resolvedIdColumn = $this->idColumn ?? StringUtils::snakeCase($propertyName) . '_id';
    }
}
