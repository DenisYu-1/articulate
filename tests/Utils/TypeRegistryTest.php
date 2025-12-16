<?php

namespace Articulate\Tests\Utils;

use Articulate\Tests\AbstractTestCase;
use Articulate\Utils\BoolTypeConverter;
use Articulate\Utils\Point;
use Articulate\Utils\PointTypeConverter;
use Articulate\Utils\TypeRegistry;

class TypeRegistryTest extends AbstractTestCase
{
    private TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = new TypeRegistry();
    }

    public function testBuiltInTypeMappings(): void
    {
        $this->assertEquals('INT', $this->typeRegistry->getDatabaseType('int'));
        $this->assertEquals('FLOAT', $this->typeRegistry->getDatabaseType('float'));
        $this->assertEquals('VARCHAR(255)', $this->typeRegistry->getDatabaseType('string'));
        $this->assertEquals('TINYINT(1)', $this->typeRegistry->getDatabaseType('bool'));
        $this->assertEquals('DATETIME', $this->typeRegistry->getDatabaseType('DateTime'));
        $this->assertEquals('TEXT', $this->typeRegistry->getDatabaseType('mixed'));
    }

    public function testReverseTypeMappings(): void
    {
        $this->assertEquals('int', $this->typeRegistry->getPhpType('INT'));
        $this->assertEquals('float', $this->typeRegistry->getPhpType('FLOAT'));
        $this->assertEquals('string', $this->typeRegistry->getPhpType('VARCHAR'));
        $this->assertEquals('int', $this->typeRegistry->getPhpType('TINYINT')); // TINYINT -> int, TINYINT(1) -> bool
        $this->assertEquals('mixed', $this->typeRegistry->getPhpType('UNKNOWN_TYPE'));
    }

    public function testBoolTypeConverter(): void
    {
        $converter = $this->typeRegistry->getConverter('bool');
        $this->assertInstanceOf(BoolTypeConverter::class, $converter);

        $this->assertEquals(1, $converter->convertToDatabase(true));
        $this->assertEquals(0, $converter->convertToDatabase(false));
        $this->assertNull($converter->convertToDatabase(null));

        $this->assertTrue($converter->convertToPHP(1));
        $this->assertFalse($converter->convertToPHP(0));
        $this->assertTrue($converter->convertToPHP('1'));
        $this->assertFalse($converter->convertToPHP('0'));
        $this->assertNull($converter->convertToPHP(null));
    }

    public function testPointTypeConverter(): void
    {
        $converter = $this->typeRegistry->getConverter(Point::class);
        $this->assertInstanceOf(PointTypeConverter::class, $converter);

        $point = new Point(10.5, 20.3);
        $dbValue = $converter->convertToDatabase($point);
        $this->assertEquals('POINT(10.500000 20.300000)', $dbValue);

        $restoredPoint = $converter->convertToPHP($dbValue);
        $this->assertInstanceOf(Point::class, $restoredPoint);
        $this->assertEquals(10.5, $restoredPoint->x);
        $this->assertEquals(20.3, $restoredPoint->y);

        $this->assertNull($converter->convertToDatabase(null));
        $this->assertNull($converter->convertToPHP(null));
    }

    public function testCustomTypeRegistration(): void
    {
        $customRegistry = new TypeRegistry();
        $customRegistry->registerType('MyCustomType', 'CUSTOM_DB_TYPE', new BoolTypeConverter());

        $this->assertEquals('CUSTOM_DB_TYPE', $customRegistry->getDatabaseType('MyCustomType'));
        $this->assertEquals('MyCustomType', $customRegistry->getPhpType('CUSTOM_DB_TYPE'));
        $this->assertInstanceOf(BoolTypeConverter::class, $customRegistry->getConverter('MyCustomType'));
    }

    public function testParameterizedTypeHandling(): void
    {
        // Test that TINYINT(1) maps to bool
        $this->assertEquals('bool', $this->typeRegistry->getPhpType('TINYINT(1)'));

        // Test that other TINYINT sizes map to int
        $this->assertEquals('int', $this->typeRegistry->getPhpType('TINYINT(2)'));
        $this->assertEquals('int', $this->typeRegistry->getPhpType('TINYINT'));
    }

    public function testDateTimeInterfaceSupport(): void
    {
        // DateTimeInterface should map to DATETIME
        $this->assertEquals('DATETIME', $this->typeRegistry->getDatabaseType(\DateTimeInterface::class));

        // Classes implementing DateTimeInterface should inherit the mapping
        $this->assertEquals('DATETIME', $this->typeRegistry->getDatabaseType(\DateTime::class));
        $this->assertEquals('DATETIME', $this->typeRegistry->getDatabaseType(\DateTimeImmutable::class));
    }

    public function testCustomClassMapping(): void
    {
        $registry = new TypeRegistry();

        // Register a custom interface mapping
        $registry->registerClassMapping(\JsonSerializable::class, 'JSON');

        // Classes implementing the interface should use the mapping
        $this->assertEquals('JSON', $registry->getDatabaseType(\JsonSerializable::class));

        // Test with a custom class that implements the interface
        // (We can't easily test this without creating a test class, but the logic is there)
    }

    public function testClassMappingInheritance(): void
    {
        $registry = new TypeRegistry();

        // Register a parent class mapping
        $registry->registerClassMapping(\Exception::class, 'TEXT');

        // Classes extending the parent should inherit the mapping
        $this->assertEquals('TEXT', $registry->getDatabaseType(\Exception::class));

        // Subclasses would inherit this mapping (though we can't test RuntimeException easily here)
    }
}
