<?php

namespace Norm\Attributes\Reflection;

use Exception;
use Norm\Attributes\Relations\ManyToOne;
use Norm\Attributes\Relations\RelationAttributeInterface;
use ReflectionProperty as BaseReflectionProperty;
use RuntimeException;

class ReflectionRelation implements PropertyInterface
{
    public function __construct(
        private readonly RelationAttributeInterface $entityProperty,
        private readonly BaseReflectionProperty $property,
    ) {
    }

    public function getTargetEntity(): string
    {
        if ($this->entityProperty->getTargetEntity()) {
            return $this->entityProperty->getTargetEntity();
        }
        $type = $this->property->getType();

        if ($type && !$type->isBuiltin()) {
            $reflectionEntity = new ReflectionEntity($type->getName());
            if (!$reflectionEntity->isEntity()) {
                throw new RuntimeException('Non-entity found in relation');
            }
            return $type->getName();
        }
        throw new Exception('Target entity is misconfigured');
    }

    public function getMappedBy(): string
    {
        if (!($this->entityProperty instanceof ManyToOne) && empty($this->entityProperty->mappedBy) && empty($this->entityProperty->inversedBy)) {
            throw new Exception('Either mappedBy or inversedBy is required');
        }
        if (!empty($this->entityProperty->mappedBy) && !empty($this->entityProperty->inversedBy)) {
            throw new Exception('mappedBy and inversedBy cannot be specified at the same time');
        }
        return $this->entityProperty->mappedBy ?? $this->parseColumnName($this->property->getName());
    }

    public function getInversedBy(): string
    {
        if (!($this->entityProperty instanceof ManyToOne) && empty($this->entityProperty->mappedBy) && empty($this->entityProperty->inversedBy)) {
            throw new Exception('Either mappedBy or inversedBy is required');
        }
        if (!empty($this->entityProperty->mappedBy) && !empty($this->entityProperty->inversedBy)) {
            throw new Exception('mappedBy and inversedBy cannot be specified at the same time');
        }
        $class = $this->property->class;
        $array = explode('\\', $class);
        return $this->entityProperty->inversedBy ?? $this->parseColumnName(end($array));
    }

    public function isForeignKeyRequired(): bool
    {
        return $this->entityProperty->foreignKey ?? true;
    }

    private function parseColumnName(string $name): string
    {
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $name)) . '_id';
    }

    public function getColumnName(): string
    {
        return $this->entityProperty->getColumn() ?? $this->parseColumnName($this->property->getName());
    }

    public function isNullable(): bool
    {
        return false;
    }

    public function getType(): string
    {
        return 'int';
    }

    public function getDefaultValue(): ?string
    {
        return null;
    }

    public function getLength(): ?int
    {
        return null;
    }
}
