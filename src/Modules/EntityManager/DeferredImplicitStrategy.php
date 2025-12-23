<?php

namespace Articulate\Modules\EntityManager;

class DeferredImplicitStrategy implements ChangeTrackingStrategy
{
    private array $originalData = [];

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
        // TODO: Implement proper data extraction based on entity metadata
        // For now, extract public properties for testing purposes
        $data = [];
        $reflection = new \ReflectionClass($entity);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $data[$name] = $entity->$name;
        }

        return $data;
    }

    private function calculateDifferences(array $original, array $current): array
    {
        $changes = [];

        foreach ($current as $field => $value) {
            if (!array_key_exists($field, $original) || $original[$field] !== $value) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }
}
