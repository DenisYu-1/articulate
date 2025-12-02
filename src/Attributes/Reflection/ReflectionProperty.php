<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Property;
use ReflectionProperty as BaseReflectionProperty;

readonly class ReflectionProperty implements PropertyInterface
{
    public function __construct(
        private Property $entityProperty,
        private BaseReflectionProperty $property,
        private bool $autoIncrement = false,
        private bool $primaryKey = false,
    ) {
    }

    public function getFieldName(): string
    {
        return $this->property->getName();
    }

    public function getColumnName(): string
    {
        return $this->entityProperty->name ?? $this->parseColumnName();
    }

    public function isNullable(): bool
    {
        return $this->entityProperty->nullable ?? $this->property->getType()->allowsNull();
    }

    public function getType(): string
    {
        return $this->entityProperty->type ?? $this->property->getType();
    }

    public function getDefaultValue(): ?string
    {
        return $this->entityProperty->defaultValue;
    }

    public function getLength(): ?int
    {
        return $this->entityProperty->maxLength;
    }

    private function parseColumnName(): string
    {
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $this->property->getName()));
    }
}
