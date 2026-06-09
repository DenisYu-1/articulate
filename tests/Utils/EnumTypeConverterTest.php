<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\EnumTypeConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

enum EnumConverterStatus: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

enum EnumConverterPriority: int {
    case Low = 1;
    case High = 5;
}

enum EnumConverterColor {
    case Red;
    case Green;
}

class EnumTypeConverterTest extends TestCase {
    public function testRejectsNonEnumClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (intentionally passing a non-enum class)
        new EnumTypeConverter(\stdClass::class);
    }

    public function testStringBackedToDatabase(): void
    {
        $converter = new EnumTypeConverter(EnumConverterStatus::class);

        $this->assertSame('active', $converter->convertToDatabase(EnumConverterStatus::Active));
    }

    public function testStringBackedToPhp(): void
    {
        $converter = new EnumTypeConverter(EnumConverterStatus::class);

        $this->assertSame(EnumConverterStatus::Inactive, $converter->convertToPHP('inactive'));
    }

    public function testIntBackedToDatabase(): void
    {
        $converter = new EnumTypeConverter(EnumConverterPriority::class);

        $this->assertSame(5, $converter->convertToDatabase(EnumConverterPriority::High));
    }

    public function testIntBackedFromNumericString(): void
    {
        $converter = new EnumTypeConverter(EnumConverterPriority::class);

        // PDO often returns integers as numeric strings
        $this->assertSame(EnumConverterPriority::Low, $converter->convertToPHP('1'));
    }

    public function testPureEnumUsesCaseName(): void
    {
        $converter = new EnumTypeConverter(EnumConverterColor::class);

        $this->assertSame('Red', $converter->convertToDatabase(EnumConverterColor::Red));
        $this->assertSame(EnumConverterColor::Green, $converter->convertToPHP('Green'));
    }

    public function testNullRoundTrips(): void
    {
        $converter = new EnumTypeConverter(EnumConverterStatus::class);

        $this->assertNull($converter->convertToDatabase(null));
        $this->assertNull($converter->convertToPHP(null));
    }

    public function testUnknownCaseThrows(): void
    {
        $converter = new EnumTypeConverter(EnumConverterColor::class);

        $this->expectException(InvalidArgumentException::class);
        $converter->convertToPHP('Purple');
    }

    public function testAlreadyHydratedValuePassesThrough(): void
    {
        $converter = new EnumTypeConverter(EnumConverterStatus::class);

        $this->assertSame(
            EnumConverterStatus::Active,
            $converter->convertToPHP(EnumConverterStatus::Active),
        );
    }
}
