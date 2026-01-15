<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\Point;
use Articulate\Utils\PointTypeConverter;
use PHPUnit\Framework\TestCase;

class PointTypeConverterTest extends TestCase {
    private PointTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new PointTypeConverter();
    }

    public function testConvertToDatabaseWithPointObject(): void
    {
        $point = new Point(10.5, -20.3);
        $result = $this->converter->convertToDatabase($point);

        $this->assertSame('POINT(10.500000 -20.300000)', $result);
    }

    public function testConvertToDatabaseWithNull(): void
    {
        $this->assertNull($this->converter->convertToDatabase(null));
    }

    public function testConvertToDatabaseThrowsExceptionForInvalidType(): void
    {
        $invalidValues = [
            'string',
            123,
            true,
            ['array'],
            new \stdClass(),
        ];

        foreach ($invalidValues as $invalidValue) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Value must be a Point instance');
            $this->converter->convertToDatabase($invalidValue);
        }
    }

    public function testConvertToPHPWithValidPointString(): void
    {
        $pointString = 'POINT(15.75 -42.123)';
        $result = $this->converter->convertToPHP($pointString);

        $this->assertInstanceOf(Point::class, $result);
        $this->assertSame(15.75, $result->x);
        $this->assertSame(-42.123, $result->y);
    }

    public function testConvertToPHPWithNull(): void
    {
        $this->assertNull($this->converter->convertToPHP(null));
    }

    public function testConvertToPHPThrowsExceptionForInvalidType(): void
    {
        $invalidValues = [
            123,
            true,
            ['array'],
            new \stdClass(),
            new Point(1.0, 2.0), // Point object should be converted first
        ];

        foreach ($invalidValues as $invalidValue) {
            if (is_string($invalidValue)) {
                continue; // Skip strings for this test
            }

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Database value must be a POINT string');
            $this->converter->convertToPHP($invalidValue);
        }
    }

    public function testConvertToPHPThrowsExceptionForInvalidPointString(): void
    {
        $invalidStrings = [
            'INVALID(10.5 20.3)',
            'POINT(10.5)',
            'POINT(10.5 20.3 30.4)',
            'POINT()',
            'POINT(abc def)',
        ];

        foreach ($invalidStrings as $invalidString) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid POINT format');
            $this->converter->convertToPHP($invalidString);
        }
    }

    public function testRoundTripConversion(): void
    {
        $originalPoint = new Point(12.34, 56.78);

        // Convert to database format
        $dbValue = $this->converter->convertToDatabase($originalPoint);

        // Convert back to PHP
        $phpValue = $this->converter->convertToPHP($dbValue);

        $this->assertInstanceOf(Point::class, $phpValue);
        $this->assertSame($originalPoint->x, $phpValue->x);
        $this->assertSame($originalPoint->y, $phpValue->y);
    }

    public function testConvertToPHPWithDifferentValidFormats(): void
    {
        $testCases = [
            ['POINT(0.0 0.0)', 0.0, 0.0],
            ['POINT(123.456 789.012)', 123.456, 789.012],
            ['POINT(-45.67 89.01)', -45.67, 89.01],
        ];

        foreach ($testCases as [$pointString, $expectedX, $expectedY]) {
            $point = $this->converter->convertToPHP($pointString);
            $this->assertSame($expectedX, $point->x);
            $this->assertSame($expectedY, $point->y);
        }
    }

    public function testConvertToPHPWithScientificNotation(): void
    {
        // Test scientific notation in POINT strings
        $point = $this->converter->convertToPHP('POINT(1.23e2 4.56e-3)');
        $this->assertSame(123.0, $point->x);
        $this->assertSame(0.00456, $point->y);
    }

    public function testConvertToPHPWithVeryLargeNumbers(): void
    {
        // Test with very large coordinate values
        $point = $this->converter->convertToPHP('POINT(999999.999999 -999999.999999)');
        $this->assertSame(999999.999999, $point->x);
        $this->assertSame(-999999.999999, $point->y);
    }

    public function testConvertToPHPWithZeroCoordinates(): void
    {
        $point = $this->converter->convertToPHP('POINT(0 0)');
        $this->assertSame(0.0, $point->x);
        $this->assertSame(0.0, $point->y);
    }

    public function testConvertToDatabaseWithIntegerCoordinates(): void
    {
        // Test with integer values (should be converted to float)
        $point = new Point(5, 10);
        $result = $this->converter->convertToDatabase($point);

        $this->assertSame('POINT(5.000000 10.000000)', $result);
    }
}
