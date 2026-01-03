<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionProperty;

class DeferredImplicitStrategy implements ChangeTrackingStrategy {
    private array $originalData = [];

    public function __construct(
        private readonly EntityMetadataRegistry $metadataRegistry
    ) {
    }

    public function trackEntity(object $entity, array $originalData): void
    {
        $oid = spl_object_id($entity);
        $this->originalData[$oid] = $originalData;
    }

    public function computeChangeSet(object $entity): array
    {
        $oid = spl_object_id($entity);

        if (!isset($this->originalData[$oid])) {
            return []; // No original data to compare against
        }

        $originalData = $this->originalData[$oid];
        $currentData = $this->extractEntityData($entity);

        return $this->calculateDifferences($originalData, $currentData);
    }

    private function extractEntityData(object $entity): array
    {
        $entityClass = $entity::class;
        $metadata = $this->metadataRegistry->getMetadata($entityClass);

        $data = [];

        // Only extract data for properties defined in entity metadata (#[Property] attributes)
        foreach ($metadata->getProperties() as $property) {
            $propertyName = $property->getFieldName();
            $columnName = $property->getColumnName();

            // Get the current value from the entity
            $currentValue = $this->getPropertyValue($entity, $property);

            // Store by column name for database operations
            $data[$columnName] = $currentValue;
        }

        return $data;
    }

    private function getPropertyValue(object $entity, ReflectionProperty $property): mixed
    {
        $propertyName = $property->getFieldName();

        // Use reflection to access the property value
        $reflectionProperty = new \ReflectionProperty($entity, $propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($entity);
    }

    private function calculateDifferences(array $original, array $current): array
    {
        $changes = [];

        // Compare current data with original data
        // Both arrays should have column names as keys
        foreach ($current as $column => $value) {
            // Check if the column exists in original data and if the value has changed
            if (!array_key_exists($column, $original) || $original[$column] !== $value) {
                $changes[$column] = $value;
            }
        }

        return $changes;
    }
}
