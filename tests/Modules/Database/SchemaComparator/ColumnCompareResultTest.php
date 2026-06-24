<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColumnCompareResultTest extends TestCase {
    #[DataProvider('equivalentDateTimeProvider')]
    public function testDateTimeTypeFormsCompareEqual(string $propertyType, string $columnType, ?string $databaseType = null): void
    {
        $result = new ColumnCompareResult(
            'occurred_at',
            CompareResult::OPERATION_UPDATE,
            new PropertiesData($propertyType, true),
            new PropertiesData($columnType, true, databaseType: $databaseType),
        );

        $this->assertTrue($result->typeMatch);
        $this->assertFalse($result->hasChanges());
    }

    public static function equivalentDateTimeProvider(): array
    {
        return [
            ['datetime', 'string', 'TIMESTAMP'],
            ['DateTime', 'string', 'timestamp without time zone'],
            ['DateTimeImmutable', 'string', 'DATETIME'],
            ['DateTimeInterface', 'DateTime', null],
            ['datetime', 'TIMESTAMP', null],
        ];
    }

    #[DataProvider('equivalentVarcharProvider')]
    public function testVarcharTypeFormsCompareEqual(?int $propertyLength, ?int $columnLength, ?string $databaseType): void
    {
        $result = new ColumnCompareResult(
            'name',
            CompareResult::OPERATION_UPDATE,
            new PropertiesData('string', false, null, $propertyLength),
            new PropertiesData('string', false, null, $columnLength, databaseType: $databaseType),
        );

        $this->assertTrue($result->typeMatch);
        $this->assertTrue($result->isLengthMatch);
        $this->assertFalse($result->hasChanges());
    }

    public static function equivalentVarcharProvider(): array
    {
        return [
            [255, 255, 'VARCHAR(255)'],
            [null, 255, 'varchar'],
            [120, 120, 'character varying'],
            [120, 120, 'VARCHAR'],
        ];
    }

    public function testDifferentExplicitVarcharLengthsStillDiff(): void
    {
        $result = new ColumnCompareResult(
            'name',
            CompareResult::OPERATION_UPDATE,
            new PropertiesData('string', false, null, 120),
            new PropertiesData('string', false, null, 255, databaseType: 'VARCHAR(255)'),
        );

        $this->assertTrue($result->typeMatch);
        $this->assertFalse($result->isLengthMatch);
        $this->assertTrue($result->hasChanges());
    }

    public function testTextAndDefaultStringDoNotCompareEqual(): void
    {
        $result = new ColumnCompareResult(
            'body',
            CompareResult::OPERATION_UPDATE,
            new PropertiesData('string', false, null, null),
            new PropertiesData('string', false, null, null, databaseType: 'TEXT'),
        );

        $this->assertFalse($result->typeMatch);
        $this->assertTrue($result->hasChanges());
    }

    public function testDifferentLogicalTypesStillDiff(): void
    {
        $result = new ColumnCompareResult(
            'name',
            CompareResult::OPERATION_UPDATE,
            new PropertiesData('int', false),
            new PropertiesData('string', false, null, 255, databaseType: 'VARCHAR(255)'),
        );

        $this->assertFalse($result->typeMatch);
        $this->assertTrue($result->hasChanges());
    }
}
