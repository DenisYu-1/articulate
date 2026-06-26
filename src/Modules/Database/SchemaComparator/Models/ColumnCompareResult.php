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
        $this->typeMatch = ColumnComparisonNormalizer::typesMatch($this->propertyData, $this->columnData);
        $this->isNullableMatch = $this->propertyData->isNullable === $this->columnData->isNullable;
        $this->isDefaultValueMatch = $this->propertyData->defaultValue === $this->columnData->defaultValue;
        $this->isLengthMatch = ColumnComparisonNormalizer::lengthsMatch($this->propertyData, $this->columnData);
    }

    public function hasChanges(): bool
    {
        return !$this->typeMatch || !$this->isNullableMatch || !$this->isDefaultValueMatch || !$this->isLengthMatch;
    }
}
