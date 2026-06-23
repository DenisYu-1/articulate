<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

class ColumnCompareResult extends CompareResult {
    public readonly bool $typeMatch;

    public readonly bool $isNullableMatch;

    public readonly bool $isDefaultValueMatch;

    public readonly bool $isLengthMatch;

    public function __construct(
        string $name,
        string $operation,
        public readonly ?PropertiesData $propertyData,
        public readonly ?PropertiesData $columnData,
    ) {
        parent::__construct($name, $operation);
        $this->typeMatch = $this->typesMatch($this->propertyData, $this->columnData);
        $this->isNullableMatch = $this->propertyData->isNullable === $this->columnData->isNullable;
        $this->isDefaultValueMatch = $this->propertyData->defaultValue === $this->columnData->defaultValue;
        $this->isLengthMatch = $this->propertyData->length === $this->columnData->length;
    }

    public function hasChanges(): bool
    {
        return !$this->typeMatch || !$this->isNullableMatch || !$this->isDefaultValueMatch || !$this->isLengthMatch;
    }

    private function typesMatch(PropertiesData $propertyData, PropertiesData $columnData): bool
    {
        if ($this->normalizeComparableType($propertyData->type) === $this->normalizeComparableType($columnData->type)) {
            return true;
        }

        if ($propertyData->type !== 'int' || $columnData->type === null) {
            return false;
        }

        if (!$propertyData->isPrimaryKey && !$propertyData->isAutoIncrement && !$propertyData->isForeignKey) {
            return false;
        }

        return strtolower($columnData->type) === 'int unsigned';
    }

    private function normalizeComparableType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if (in_array($type, ['DateTime', 'DateTimeImmutable', 'DateTimeInterface'], true)) {
            return 'DateTime';
        }

        return $type;
    }
}
