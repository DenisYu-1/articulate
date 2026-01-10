<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\BoolTypeConverter;
use PHPUnit\Framework\TestCase;

class BoolTypeConverterTest extends TestCase
{
    private BoolTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new BoolTypeConverter();
    }

    public function testConvertToDatabaseWithTrue(): void
    {
        $this->assertSame(1, $this->converter->convertToDatabase(true));
    }

    public function testConvertToDatabaseWithFalse(): void
    {
        $this->assertSame(0, $this->converter->convertToDatabase(false));
    }

    public function testConvertToDatabaseWithNull(): void
    {
        $this->assertNull($this->converter->convertToDatabase(null));
    }

    public function testConvertToDatabaseWithTruthyValues(): void
    {
        $this->assertSame(1, $this->converter->convertToDatabase(1));
        $this->assertSame(1, $this->converter->convertToDatabase('string'));
        $this->assertSame(1, $this->converter->convertToDatabase([1, 2, 3]));
    }

    public function testConvertToDatabaseWithFalsyValues(): void
    {
        $this->assertSame(0, $this->converter->convertToDatabase(0));
        $this->assertSame(0, $this->converter->convertToDatabase(''));
        $this->assertSame(0, $this->converter->convertToDatabase([]));
    }

    public function testConvertToPHPWithOne(): void
    {
        $this->assertTrue($this->converter->convertToPHP(1));
    }

    public function testConvertToPHPWithZero(): void
    {
        $this->assertFalse($this->converter->convertToPHP(0));
    }

    public function testConvertToPHPWithNull(): void
    {
        $this->assertNull($this->converter->convertToPHP(null));
    }

    public function testConvertToPHPWithVariousTruthyValues(): void
    {
        $this->assertTrue($this->converter->convertToPHP(2));
        $this->assertTrue($this->converter->convertToPHP(-1));
        $this->assertTrue($this->converter->convertToPHP('1'));
        $this->assertTrue($this->converter->convertToPHP('true'));
        $this->assertTrue($this->converter->convertToPHP([1]));
    }

    public function testConvertToPHPWithVariousFalsyValues(): void
    {
        $this->assertFalse($this->converter->convertToPHP(0));
        $this->assertFalse($this->converter->convertToPHP(''));
        $this->assertFalse($this->converter->convertToPHP([]));
        // null input returns null (not false)
        $this->assertNull($this->converter->convertToPHP(null));
    }

    public function testConvertToPHPWithStringOne(): void
    {
        $this->assertTrue($this->converter->convertToPHP('1'));
    }

    public function testConvertToPHPWithStringZero(): void
    {
        $this->assertFalse($this->converter->convertToPHP('0'));
    }

    public function testRoundTripConversion(): void
    {
        // Test true -> 1 -> true
        $dbValue = $this->converter->convertToDatabase(true);
        $phpValue = $this->converter->convertToPHP($dbValue);
        $this->assertTrue($phpValue);

        // Test false -> 0 -> false
        $dbValue = $this->converter->convertToDatabase(false);
        $phpValue = $this->converter->convertToPHP($dbValue);
        $this->assertFalse($phpValue);
    }

    public function testNullHandling(): void
    {
        // Null should remain null in both directions
        $this->assertNull($this->converter->convertToDatabase(null));
        $this->assertNull($this->converter->convertToPHP(null));
    }
}