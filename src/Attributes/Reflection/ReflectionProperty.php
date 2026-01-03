<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Property;
use ReflectionProperty as BaseReflectionProperty;

readonly class ReflectionProperty implements PropertyInterface {
    public function __construct(
        private Property $entityProperty,
        private BaseReflectionProperty $property,
        private bool $autoIncrement = false,
        private bool $primaryKey = false,
        private ?string $generatorType = null,
        private ?string $sequence = null,
        private ?array $generatorOptions = null,
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
        return $this->entityProperty->type ?? $this->property->getType()?->getName() ?? 'mixed';
    }

    public function getDefaultValue(): ?string
    {
        return $this->entityProperty->defaultValue;
    }

    public function getLength(): ?int
    {
        return $this->entityProperty->maxLength;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function isPrimaryKey(): bool
    {
        return $this->primaryKey;
    }

    public function getGeneratorType(): ?string
    {
        return $this->generatorType;
    }

    public function getSequence(): ?string
    {
        return $this->sequence;
    }

    public function getGeneratorOptions(): ?array
    {
        return $this->generatorOptions;
    }

    private function parseColumnName(): string
    {
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $this->property->getName()));
    }
}
