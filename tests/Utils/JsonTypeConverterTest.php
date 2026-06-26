<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\JsonTypeConverter;
use JsonException;
use PHPUnit\Framework\TestCase;

class JsonTypeConverterTest extends TestCase {
    private JsonTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new JsonTypeConverter();
    }

    public function testConvertToDatabaseNull(): void
    {
        $this->assertNull($this->converter->convertToDatabase(null));
    }

    public function testConvertToDatabaseArray(): void
    {
        $result = $this->converter->convertToDatabase(['key' => 'value', 'num' => 42]);
        $this->assertSame('{"key":"value","num":42}', $result);
    }

    public function testConvertToDatabaseEmptyArray(): void
    {
        $this->assertSame('[]', $this->converter->convertToDatabase([]));
    }

    public function testConvertToDatabaseNestedArray(): void
    {
        $input = ['a' => ['b' => ['c' => 1]]];
        $result = $this->converter->convertToDatabase($input);
        $this->assertSame('{"a":{"b":{"c":1}}}', $result);
    }

    public function testConvertToPHPNull(): void
    {
        $this->assertNull($this->converter->convertToPHP(null));
    }

    public function testConvertToPHPJsonString(): void
    {
        $result = $this->converter->convertToPHP('{"key":"value","num":42}');
        $this->assertSame(['key' => 'value', 'num' => 42], $result);
    }

    public function testConvertToPHPEmptyJsonObject(): void
    {
        $this->assertSame([], $this->converter->convertToPHP('{}'));
    }

    public function testConvertToPHPEmptyJsonArray(): void
    {
        $this->assertSame([], $this->converter->convertToPHP('[]'));
    }

    public function testConvertToPHPAlreadyDecodedArray(): void
    {
        // Some drivers return already-decoded arrays; converter must pass them through
        $input = ['already' => 'decoded'];
        $this->assertSame($input, $this->converter->convertToPHP($input));
    }

    public function testConvertToPHPInvalidJsonThrows(): void
    {
        $this->expectException(JsonException::class);
        $this->converter->convertToPHP('{invalid json}');
    }

    public function testRoundTrip(): void
    {
        $original = ['tags' => ['php', 'orm'], 'meta' => ['count' => 2]];
        $db = $this->converter->convertToDatabase($original);
        $back = $this->converter->convertToPHP($db);
        $this->assertSame($original, $back);
    }
}
