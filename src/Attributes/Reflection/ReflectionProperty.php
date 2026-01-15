<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Property;
use ReflectionProperty as BaseReflectionProperty;

class ReflectionProperty implements PropertyInterface {
    public function __construct(
        private readonly Property $entityProperty,
        private readonly BaseReflectionProperty $property,
        private readonly bool $autoIncrement = false,
        private readonly bool $primaryKey = false,
        private readonly ?string $generatorType = null,
        private readonly ?string $sequence = null,
        private readonly ?array $generatorOptions = null,
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

    /**
     * Get the value of this property from an entity instance.
     */
    public function getValue(object $entity): mixed
    {
        $reflectionProperty = $this->property;
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($entity);
    }

    /**
     * Set the value of this property on an entity instance.
     */
    public function setValue(object $entity, mixed $value): void
    {
        $reflectionProperty = $this->property;
        $reflectionProperty->setAccessible(true);

        $reflectionProperty->setValue($entity, $value);
    }

    private function parseColumnName(): string
    {
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $this->property->getName()));
    }
}
