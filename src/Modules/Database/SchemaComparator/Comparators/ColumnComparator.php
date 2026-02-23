<?php

namespace Articulate\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use RuntimeException;

class ColumnComparator {
    /**
     * @param array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     *     generatorType: ?string,
     *     sequence: ?string,
     *     isPrimaryKey: bool,
     *     isAutoIncrement: bool
     * }> $propertiesIndexed
     * @param array<string, object> $columnsIndexed
     * @return array<ColumnCompareResult>
     */
    public function compareColumns(array $propertiesIndexed, array $columnsIndexed): array
    {
        $columnsToDelete = array_diff_key($columnsIndexed, $propertiesIndexed);
        $columnsToCreate = array_diff_key($propertiesIndexed, $columnsIndexed);
        $columnsToUpdate = array_intersect_key($propertiesIndexed, $columnsIndexed);

        $results = [];

        // Create columns
        foreach ($columnsToCreate as $columnName => $data) {
            $results[] = new ColumnCompareResult(
                $columnName,
                CompareResult::OPERATION_CREATE,
                new PropertiesData(
                    $data['type'],
                    $data['nullable'],
                    $data['default'],
                    $data['length'],
                    $data['generatorType'],
                    $data['sequence'],
                    $data['isPrimaryKey'],
                    $data['isAutoIncrement'],
                ),
                new PropertiesData(),
            );
        }

        // Update columns
        foreach ($columnsToUpdate as $columnName => $data) {
            $column = $columnsIndexed[$columnName];
            $result = new ColumnCompareResult(
                $columnName,
                CompareResult::OPERATION_UPDATE,
                new PropertiesData(
                    $data['type'],
                    $data['nullable'],
                    $data['default'],
                    $data['length'],
                ),
                new PropertiesData(
                    $column->type,
                    $column->isNullable,
                    $column->defaultValue,
                    $column->length,
                ),
            );
            if (!$result->typeMatch || !$result->isNullableMatch || !$result->isDefaultValueMatch || !$result->isLengthMatch) {
                $results[] = $result;
            }
        }

        // Delete columns
        foreach ($columnsToDelete as $columnName => $column) {
            $results[] = new ColumnCompareResult(
                $columnName,
                CompareResult::OPERATION_DELETE,
                new PropertiesData(),
                new PropertiesData(
                    $column->type,
                    $column->isNullable,
                    $column->defaultValue,
                    $column->length,
                ),
            );
        }

        return $results;
    }

    /**
     * @param array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     *     generatorType: ?string,
     *     sequence: ?string,
     *     isPrimaryKey: bool,
     *     isAutoIncrement: bool
     * }> $propertiesIndexed
     * @return array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     *     generatorType: ?string,
     *     sequence: ?string,
     *     isPrimaryKey: bool,
     *     isAutoIncrement: bool
     * }>
     */
    public function mergeColumnDefinition(array $propertiesIndexed, string $columnName, ReflectionProperty|ReflectionRelation $property, string $tableName): array
    {
        $incoming = $this->buildColumnProperties($property);

        if (!isset($propertiesIndexed[$columnName])) {
            $propertiesIndexed[$columnName] = $incoming;

            return $propertiesIndexed;
        }

        $existing = $propertiesIndexed[$columnName];

        $this->validateColumnConflicts($incoming, $existing, $columnName, $tableName);
        $this->validateRelationConflicts($incoming, $existing, $columnName, $tableName);

        $propertiesIndexed[$columnName] = $this->mergeColumnProperties($incoming, $existing);

        return $propertiesIndexed;
    }

    /**
     * Builds column properties array from a property or relation.
     */
    private function buildColumnProperties(ReflectionProperty|ReflectionRelation $property): array
    {
        return [
            'type' => $this->normalizeTypeName($property->getType()),
            'nullable' => $property->isNullable(),
            'default' => $property->getDefaultValue(),
            'length' => $property->getLength(),
            'relation' => $property instanceof ReflectionRelation ? $property : null,
            'foreignKeyRequired' => $property instanceof ReflectionRelation ? $property->isForeignKeyRequired() : false,
            'referencedColumn' => $property instanceof ReflectionRelation ? $property->getReferencedColumnName() : null,
            'generatorType' => $property instanceof ReflectionProperty ? $property->getGeneratorType() : null,
            'sequence' => $property instanceof ReflectionProperty ? $property->getSequence() : null,
            'isPrimaryKey' => $property instanceof ReflectionProperty ? $property->isPrimaryKey() : false,
            'isAutoIncrement' => $property instanceof ReflectionProperty ? $property->isAutoIncrement() : false,
        ];
    }

    /**
     * Validates column conflicts (type, length, default value mismatches).
     */
    private function validateColumnConflicts(array $incoming, array $existing, string $columnName, string $tableName): void
    {
        if ($incoming['type'] !== $existing['type'] || $incoming['length'] !== $existing['length'] || $incoming['default'] !== $existing['default']) {
            throw new RuntimeException(
                sprintf(
                    'Column "%s" on table "%s" conflicts between entities',
                    $columnName,
                    $tableName,
                ),
            );
        }
    }

    /**
     * Validates relation-specific conflicts.
     */
    private function validateRelationConflicts(array $incoming, array $existing, string $columnName, string $tableName): void
    {
        if (($incoming['relation'] !== null) !== ($existing['relation'] !== null)) {
            throw new RuntimeException(
                sprintf(
                    'Column "%s" on table "%s" conflicts between relation and scalar definitions',
                    $columnName,
                    $tableName,
                ),
            );
        }

        if ($incoming['relation'] && $existing['relation']) {
            $incomingTargetClass = $incoming['relation']->getTargetEntity();
            $existingTargetClass = $existing['relation']->getTargetEntity();

            // Skip comparison for relations that don't have specific target entities (like MorphTo)
            if ($incomingTargetClass !== null && $existingTargetClass !== null) {
                $incomingTarget = new ReflectionEntity($incomingTargetClass);
                $existingTarget = new ReflectionEntity($existingTargetClass);
                if ($incomingTarget->getTableName() !== $existingTarget->getTableName() || $incoming['referencedColumn'] !== $existing['referencedColumn']) {
                    throw new RuntimeException(
                        sprintf(
                            'Relation column "%s" on table "%s" points to different targets',
                            $columnName,
                            $tableName,
                        ),
                    );
                }
            }
        }
    }

    /**
     * Merges column properties from incoming and existing definitions.
     */
    private function mergeColumnProperties(array $incoming, array $existing): array
    {
        return [
            'type' => $existing['type'],
            'nullable' => $existing['nullable'] || $incoming['nullable'],
            'default' => $existing['default'],
            'length' => $existing['length'],
            'relation' => $existing['relation'] ?? $incoming['relation'],
            'foreignKeyRequired' => $existing['foreignKeyRequired'] || $incoming['foreignKeyRequired'],
            'referencedColumn' => $existing['referencedColumn'] ?? $incoming['referencedColumn'],
            'generatorType' => $existing['generatorType'] ?? $incoming['generatorType'],
            'sequence' => $existing['sequence'] ?? $incoming['sequence'],
            'isPrimaryKey' => $existing['isPrimaryKey'] ?? $incoming['isPrimaryKey'],
            'isAutoIncrement' => $existing['isAutoIncrement'] ?? $incoming['isAutoIncrement'],
        ];
    }

    /**
     * @param array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     *     generatorType: ?string,
     *     sequence: ?string,
     *     isPrimaryKey: bool,
     *     isAutoIncrement: bool
     * }> $propertiesIndexed
     * @return array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     *     generatorType: ?string,
     *     sequence: ?string,
     *     isPrimaryKey: bool,
     *     isAutoIncrement: bool
     * }>
     */
    public function addMorphToColumns(array $propertiesIndexed, ReflectionRelation $relation, string $tableName): array
    {
        // Add the morph type column
        $typeColumnName = $relation->getMorphTypeColumnName();
        $typeColumnData = [
            'type' => 'string',
            'nullable' => false,
            'default' => null,
            'length' => 255,
            'relation' => null, // Type column has no relation
            'foreignKeyRequired' => false,
            'referencedColumn' => null,
            'generatorType' => null,
            'sequence' => null,
            'isPrimaryKey' => false,
            'isAutoIncrement' => false,
        ];

        if (isset($propertiesIndexed[$typeColumnName])) {
            // Merge with existing definition
            $existing = $propertiesIndexed[$typeColumnName];
            if ($typeColumnData['type'] !== $existing['type'] || $typeColumnData['length'] !== $existing['length']) {
                throw new RuntimeException(
                    sprintf('Morph type column "%s" conflicts on table "%s"', $typeColumnName, $tableName)
                );
            }
        } else {
            $propertiesIndexed[$typeColumnName] = $typeColumnData;
        }

        // Add the morph ID column
        $idColumnName = $relation->getMorphIdColumnName();
        $idColumnData = [
            'type' => 'int',
            'nullable' => false,
            'default' => null,
            'length' => null,
            'relation' => $relation, // ID column has the relation for potential future FK generation
            'foreignKeyRequired' => false, // Polymorphic relations don't use traditional FK constraints
            'referencedColumn' => $relation->getReferencedColumnName(),
            'generatorType' => null,
            'sequence' => null,
            'isPrimaryKey' => false,
            'isAutoIncrement' => false,
        ];

        if (isset($propertiesIndexed[$idColumnName])) {
            $existing = $propertiesIndexed[$idColumnName];
            if ($idColumnData['type'] !== $existing['type']) {
                throw new RuntimeException(
                    sprintf('Morph ID column "%s" conflicts on table "%s"', $idColumnName, $tableName)
                );
            }
            $propertiesIndexed[$idColumnName] = [
                'type' => $idColumnData['type'],
                'nullable' => $existing['nullable'] && $idColumnData['nullable'],
                'default' => $existing['default'],
                'length' => $existing['length'],
                'relation' => $idColumnData['relation'],
                'foreignKeyRequired' => $idColumnData['foreignKeyRequired'] || $existing['foreignKeyRequired'],
                'referencedColumn' => $idColumnData['referencedColumn'],
                'generatorType' => $existing['generatorType'],
                'sequence' => $existing['sequence'],
                'isPrimaryKey' => $existing['isPrimaryKey'],
                'isAutoIncrement' => $existing['isAutoIncrement'],
            ];
        } else {
            $propertiesIndexed[$idColumnName] = $idColumnData;
        }

        return $propertiesIndexed;
    }

    private function normalizeTypeName(string|\ReflectionType|null $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if (is_string($type)) {
            $clean = ltrim($type, '?');
            $clean = str_replace('|null', '', $clean);

            return $clean;
        }
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof \ReflectionUnionType) {
            $nonNull = array_filter(
                $type->getTypes(),
                fn ($t) => $t instanceof \ReflectionNamedType && $t->getName() !== 'null',
            );
            $first = reset($nonNull);

            return $first ? $first->getName() : null;
        }

        return (string) $type;
    }
}
