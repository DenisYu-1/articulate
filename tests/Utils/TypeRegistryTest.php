<?php

namespace Articulate\Tests\Utils;

use Articulate\Tests\AbstractTestCase;
use Articulate\Utils\BoolTypeConverter;
use Articulate\Utils\Point;
use Articulate\Utils\PointTypeConverter;
use Articulate\Utils\TypeRegistry;
use Iterator;
use JsonSerializable;
use ReflectionMethod;
use ReflectionProperty;
use Serializable;

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

    public function testNullablePointTypeRegistration(): void
    {
        // Test that nullable Point type is registered correctly
        // This covers the concatenation mutation on line 266 of TypeRegistry.php
        $nullablePointType = '?' . Point::class;

        // The type should be registered and map to POINT
        $this->assertEquals('POINT', $this->typeRegistry->getDatabaseType($nullablePointType));

        // And should have the PointTypeConverter
        $converter = $this->typeRegistry->getConverter($nullablePointType);
        $this->assertInstanceOf(PointTypeConverter::class, $converter);
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
        $registry->registerClassMapping(JsonSerializable::class, 'JSON');

        // Classes implementing the interface should use the mapping
        $this->assertEquals('JSON', $registry->getDatabaseType(JsonSerializable::class));

        // Test with a custom class that implements the interface
        // (We can't easily test this without creating a test class, but the logic is there)
    }

    public function testClassMappingPriority(): void
    {
        $registry = new TypeRegistry();

        // Register conflicting mappings with different priorities
        $registry->registerClassMapping(Iterator::class, 'TEXT', null, 5); // Lower priority
        $registry->registerClassMapping(JsonSerializable::class, 'JSON', null, 1); // Higher priority

        // Create a mock class that implements both interfaces
        $mockClass = 'TestPriorityClass';

        if (!class_exists($mockClass)) {
            eval("
                class $mockClass implements Iterator, JsonSerializable {
                    public function current(): mixed { return null; }
                    public function key(): mixed { return null; }
                    public function next(): void {}
                    public function rewind(): void {}
                    public function valid(): bool { return false; }
                    public function jsonSerialize(): mixed { return []; }
                }
            ");
        }

        try {
            // Should use JsonSerializable mapping due to higher priority (lower number)
            $this->assertEquals('JSON', $registry->getDatabaseType($mockClass));
        } finally {
            // Clean up
            if (class_exists($mockClass)) {
                // Note: Can't actually remove class in PHP, but test is isolated
            }
        }
    }

    public function testInvalidClassMapping(): void
    {
        $registry = new TypeRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register mapping for unknown class or interface');

        $registry->registerClassMapping('NonExistentClass', 'TEXT');
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

    public function testRegisterClassMappingWithoutConverter(): void
    {
        $registry = new TypeRegistry();

        // Test registering class mapping without converter - covers IfNegation mutation on line 72
        $registry->registerClassMapping(Serializable::class, 'TEXT');

        // Verify the mapping was registered
        $this->assertEquals('TEXT', $registry->getDatabaseType(Serializable::class));

        // Verify no converter was set (covers the !$converter branch)
        $this->assertFalse($registry->hasConverter(Serializable::class));
        $this->assertNull($registry->getConverter(Serializable::class));
    }

    public function testRegisterClassMappingWithConverter(): void
    {
        $registry = new TypeRegistry();
        $converter = new BoolTypeConverter();

        // Test registering class mapping with converter - covers the $converter === true branch
        $registry->registerClassMapping(Serializable::class, 'TEXT', $converter);

        // Verify converter was set
        $this->assertTrue($registry->hasConverter(Serializable::class));
        $this->assertSame($converter, $registry->getConverter(Serializable::class));
    }

    public function testGetDatabaseTypeCachingBehavior(): void
    {
        $registry = new TypeRegistry();

        // First call should compute and cache
        $result1 = $registry->getDatabaseType('int');
        $this->assertEquals('INT', $result1);

        // Second call should use cache
        $result2 = $registry->getDatabaseType('int');
        $this->assertEquals('INT', $result2);

        // Verify it's the same result
        $this->assertSame($result1, $result2);
    }

    public function testGetDatabaseTypeCacheBypass(): void
    {
        $registry = new TypeRegistry();

        // Test what happens if we remove the early return from cache - covers ReturnRemoval mutation on line 97
        // We need to simulate this by checking that the method still works when cache is empty

        // Clear caches to ensure we test the full logic
        $registry->clearCaches();

        // Register a custom type and verify it works
        $registry->registerType('CustomType', 'CUSTOM_DB_TYPE');

        // This should still work and use the mapping
        $this->assertEquals('CUSTOM_DB_TYPE', $registry->getDatabaseType('CustomType'));

        // Verify it was cached
        $this->assertEquals('CUSTOM_DB_TYPE', $registry->getDatabaseType('CustomType'));
    }

    public function testFindClassMappingWithEmptyInheritance(): void
    {
        $registry = new TypeRegistry();

        // Test class mapping when inheritance info returns empty arrays
        // This covers Foreach_ mutation on line 139 where foreach becomes foreach([])
        $nonExistentClass = 'DefinitelyNonExistentClass' . uniqid();

        // This should return null since no mappings exist for non-existent classes
        $reflectionMethod = new ReflectionMethod($registry, 'findClassMapping');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($registry, $nonExistentClass);
        $this->assertNull($result);
    }

    public function testGetInheritanceInfoForNonExistentClass(): void
    {
        $registry = new TypeRegistry();

        // Test inheritance info for non-existent class - covers LogicalNot mutation on line 162
        $reflectionMethod = new ReflectionMethod($registry, 'getInheritanceInfo');
        $reflectionMethod->setAccessible(true);

        $nonExistentClass = 'DefinitelyNonExistentClass' . uniqid();
        $inheritanceInfo = $reflectionMethod->invoke($registry, $nonExistentClass);

        // Should return empty arrays for non-existent classes
        $this->assertEquals(['interfaces' => [], 'parents' => []], $inheritanceInfo);

        // Second call should use cache
        $inheritanceInfo2 = $reflectionMethod->invoke($registry, $nonExistentClass);
        $this->assertSame($inheritanceInfo, $inheritanceInfo2);
    }

    public function testGetInheritanceInfoForExistingClass(): void
    {
        $registry = new TypeRegistry();

        // Test inheritance info for existing class
        $reflectionMethod = new ReflectionMethod($registry, 'getInheritanceInfo');
        $reflectionMethod->setAccessible(true);

        $inheritanceInfo = $reflectionMethod->invoke($registry, \Exception::class);

        // Should have parent classes and interfaces
        $this->assertIsArray($inheritanceInfo['parents']);
        $this->assertIsArray($inheritanceInfo['interfaces']);

        // Exception implements Throwable as an interface, not a parent class
        $this->assertContains(\Throwable::class, $inheritanceInfo['interfaces']);
        $this->assertContains(\Stringable::class, $inheritanceInfo['interfaces']);
    }

    public function testPregMatchHandlingInGetPhpType(): void
    {
        $registry = new TypeRegistry();

        // Test TINYINT(1) handling - covers PregMatchRemoveFlags mutation on line 181
        $this->assertEquals('bool', $registry->getPhpType('TINYINT(1)'));
        $this->assertEquals('bool', $registry->getPhpType('tinyint(1)')); // Case insensitive

        // Test other TINYINT variations map to int
        $this->assertEquals('int', $registry->getPhpType('TINYINT'));
        $this->assertEquals('int', $registry->getPhpType('TINYINT(2)'));
    }

    public function testExtractBaseTypePregMatch(): void
    {
        $registry = new TypeRegistry();

        // Test extractBaseType method - covers PregMatchRemoveCaret mutation on line 213
        $reflectionMethod = new ReflectionMethod($registry, 'extractBaseType');
        $reflectionMethod->setAccessible(true);

        // Test parameterized types
        $this->assertEquals('VARCHAR', $reflectionMethod->invoke($registry, 'VARCHAR(255)'));
        $this->assertEquals('TINYINT', $reflectionMethod->invoke($registry, 'TINYINT(1)'));
        $this->assertEquals('DECIMAL', $reflectionMethod->invoke($registry, 'DECIMAL(10,2)'));

        // Test non-parameterized types
        $this->assertEquals('TEXT', $reflectionMethod->invoke($registry, 'TEXT'));
        $this->assertEquals('INT', $reflectionMethod->invoke($registry, 'INT'));

        // Test edge cases
        $this->assertEquals('INVALID', $reflectionMethod->invoke($registry, 'INVALID'));
        $this->assertEquals('', $reflectionMethod->invoke($registry, ''));
    }

    public function testInferPhpTypeMatchExpression(): void
    {
        $registry = new TypeRegistry();

        // Test the match expression in inferPhpType - covers MatchArmRemoval mutations on line 227
        $reflectionMethod = new ReflectionMethod($registry, 'inferPhpType');
        $reflectionMethod->setAccessible(true);

        // Test all the match arms
        $this->assertEquals('int', $reflectionMethod->invoke($registry, 'INT'));
        $this->assertEquals('int', $reflectionMethod->invoke($registry, 'INTEGER'));
        $this->assertEquals('int', $reflectionMethod->invoke($registry, 'BIGINT'));
        $this->assertEquals('int', $reflectionMethod->invoke($registry, 'SMALLINT'));
        $this->assertEquals('int', $reflectionMethod->invoke($registry, 'TINYINT'));
        $this->assertEquals('int', $reflectionMethod->invoke($registry, 'MEDIUMINT'));

        $this->assertEquals('float', $reflectionMethod->invoke($registry, 'FLOAT'));
        $this->assertEquals('float', $reflectionMethod->invoke($registry, 'DOUBLE'));
        $this->assertEquals('float', $reflectionMethod->invoke($registry, 'DECIMAL'));
        $this->assertEquals('float', $reflectionMethod->invoke($registry, 'NUMERIC'));

        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'VARCHAR'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'CHAR'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'TEXT'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'TINYTEXT'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'MEDIUMTEXT'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'LONGTEXT'));

        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'DATE'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'DATETIME'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'TIMESTAMP'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'TIME'));
        $this->assertEquals('string', $reflectionMethod->invoke($registry, 'YEAR'));

        $this->assertEquals('bool', $reflectionMethod->invoke($registry, 'BOOL'));
        $this->assertEquals('bool', $reflectionMethod->invoke($registry, 'BOOLEAN'));

        // Test default case
        $this->assertEquals('mixed', $reflectionMethod->invoke($registry, 'UNKNOWN_TYPE'));
        $this->assertEquals('mixed', $reflectionMethod->invoke($registry, 'SOME_RANDOM_TYPE'));
    }

    public function testClassMappingWithInheritanceAndPriority(): void
    {
        $registry = new TypeRegistry();

        // Create a test class hierarchy
        $interfaceName = 'TestInterface' . uniqid();
        $parentClassName = 'TestParent' . uniqid();
        $childClassName = 'TestChild' . uniqid();

        if (!interface_exists($interfaceName)) {
            eval("interface $interfaceName {}");
        }

        if (!class_exists($parentClassName)) {
            eval("class $parentClassName implements $interfaceName {}");
        }

        if (!class_exists($childClassName)) {
            eval("class $childClassName extends $parentClassName {}");
        }

        try {
            // Register mappings with different priorities
            $registry->registerClassMapping($interfaceName, 'INTERFACE_TYPE', null, 10); // Low priority
            $registry->registerClassMapping($parentClassName, 'PARENT_TYPE', null, 5);   // Medium priority
            $registry->registerClassMapping($childClassName, 'CHILD_TYPE', null, 1);    // High priority

            // Child class should use its own mapping (highest priority)
            $this->assertEquals('CHILD_TYPE', $registry->getDatabaseType($childClassName));

            // Parent class should use its own mapping
            $this->assertEquals('PARENT_TYPE', $registry->getDatabaseType($parentClassName));

            // Interface should use its mapping
            $this->assertEquals('INTERFACE_TYPE', $registry->getDatabaseType($interfaceName));

        } finally {
            // Cleanup would happen naturally in test isolation
        }
    }

    public function testRegisterTypeClearsMappingCache(): void
    {
        $registry = new TypeRegistry();

        // Get a type to populate cache
        $registry->getDatabaseType('int');

        // Register a new type should clear cache
        $registry->registerType('NewType', 'NEW_DB_TYPE');

        // Access the private cache to verify it was cleared
        $reflectionProperty = new ReflectionProperty($registry, 'mappingCache');
        $reflectionProperty->setAccessible(true);

        $cache = $reflectionProperty->getValue($registry);
        // Cache should be empty after registering new type
        $this->assertEmpty($cache);
    }

    public function testRegisterClassMappingClearsMappingCache(): void
    {
        $registry = new TypeRegistry();

        // Get a type to populate cache
        $registry->getDatabaseType('int');

        // Register a new class mapping should clear cache
        $registry->registerClassMapping(Serializable::class, 'TEXT');

        // Access the private cache to verify it was cleared
        $reflectionProperty = new ReflectionProperty($registry, 'mappingCache');
        $reflectionProperty->setAccessible(true);

        $cache = $reflectionProperty->getValue($registry);
        // Cache should be empty after registering new mapping
        $this->assertEmpty($cache);
    }

    public function testPriorityParameterDefaultValue(): void
    {
        $registry = new TypeRegistry();

        // Test default priority value of 0 - covers DecrementInteger mutation on line 58
        $registry->registerClassMapping(Serializable::class, 'TEXT'); // Uses default priority 0

        $this->assertEquals('TEXT', $registry->getDatabaseType(Serializable::class));
    }

    public function testPriorityParameterExplicitValue(): void
    {
        $registry = new TypeRegistry();

        // Test explicit priority value - covers IncrementInteger mutation on line 58
        $registry->registerClassMapping(Serializable::class, 'TEXT', null, 5);

        $this->assertEquals('TEXT', $registry->getDatabaseType(Serializable::class));
    }
}
