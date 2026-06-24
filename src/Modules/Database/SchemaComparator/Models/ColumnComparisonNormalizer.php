<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

class ColumnComparisonNormalizer {
    private const DEFAULT_STRING_LENGTH = 255;

    public static function typesMatch(PropertiesData $propertyData, PropertiesData $columnData): bool
    {
        if (self::normalizeType($propertyData) === self::normalizeType($columnData)) {
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

    public static function lengthsMatch(PropertiesData $propertyData, PropertiesData $columnData): bool
    {
        if (!self::isLengthAwareString($propertyData) || !self::isLengthAwareString($columnData)) {
            return $propertyData->length === $columnData->length;
        }

        return self::effectiveLength($propertyData) === self::effectiveLength($columnData);
    }

    public static function fromDatabaseColumn(object $column): PropertiesData
    {
        return new PropertiesData(
            self::getComparableColumnType($column),
            $column->isNullable,
            $column->defaultValue,
            $column->length,
            databaseType: $column->type ?? null,
        );
    }

    private static function normalizeType(PropertiesData $data): ?string
    {
        $type = self::normalizeTypeName($data->type);
        $databaseType = self::normalizeTypeName($data->databaseType);

        if (($databaseType !== null && self::isDateTimeType($databaseType)) || ($type !== null && self::isDateTimeType($type))) {
            return 'datetime';
        }

        if ($databaseType !== null && self::isVarcharType($databaseType)) {
            return 'varchar';
        }

        if ($type !== null && self::isVarcharType($type)) {
            return $databaseType ?? 'varchar';
        }

        return $type;
    }

    private static function normalizeTypeName(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $type = trim($type);
        $type = ltrim($type, '?');
        $type = ltrim($type, '\\');
        $type = preg_replace('/\s+/', ' ', $type) ?? $type;
        $type = strtolower($type);

        if (preg_match('/^([a-z ]+)\s*\(/', $type, $matches)) {
            return trim($matches[1]);
        }

        return $type;
    }

    private static function isDateTimeType(string $type): bool
    {
        return in_array($type, [
            'datetime',
            'date_time',
            'datetimeimmutable',
            'datetimeinterface',
            'timestamp',
            'timestamp without time zone',
            'timestamp with time zone',
            'timestamptz',
        ], true);
    }

    private static function isVarcharType(string $type): bool
    {
        return in_array($type, [
            'string',
            'varchar',
            'character varying',
        ], true);
    }

    private static function isLengthAwareString(PropertiesData $data): bool
    {
        return self::normalizeType($data) === 'varchar';
    }

    private static function effectiveLength(PropertiesData $data): ?int
    {
        return $data->length ?? self::DEFAULT_STRING_LENGTH;
    }

    private static function getComparableColumnType(object $column): ?string
    {
        $type = $column->phpType ?? $column->type ?? null;
        $rawType = $column->type ?? null;

        if ($type === 'mixed' && is_string($rawType) && in_array($rawType, ['int', 'float', 'string', 'bool', 'DateTime', 'DateTimeImmutable'], true)) {
            return $rawType;
        }

        return $type;
    }
}
