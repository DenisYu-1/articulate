<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Tests\AbstractTestCase;
use Exception;
use ReflectionClass;

class PropertyTest extends AbstractTestCase {
    #[Property(type: 'string')]
    private int $typeOverwriteCheck;

    #[Property()]
    private int $defaultCheck;

    #[Property(name: 'username')]
    private ?string $nameOverwriteCheck;

    #[Property(nullable: false)]
    private string $nullableFalse;

    #[Property(nullable: true)]
    private string $nullableTrue;

    #[Property]
    private string $nullableBasedOnFieldFalse;

    #[Property]
    private ?string $nullableBasedOnFieldTrue;

    #[Property]
    private ?string $defaultValueBasedOnFieldNull;

    #[Property(defaultValue: 'test')]
    private ?string $defaultValueOverwriteCheck;

    public function testTypeOverwrite()
    {
        $propertyToTest = $this->getReflectionProperty('typeOverwriteCheck');

        $this->assertEquals('string', $propertyToTest->getType());
    }

    public function testType()
    {
        $propertyToTest = $this->getReflectionProperty('defaultCheck');

        $this->assertEquals('int', $propertyToTest->getType());
    }

    public function testNameOverwrite()
    {
        $propertyToTest = $this->getReflectionProperty('nameOverwriteCheck');

        $this->assertEquals('username', $propertyToTest->getColumnName());
    }

    public function testName()
    {
        $propertyToTest = $this->getReflectionProperty('defaultCheck');

        $this->assertEquals('default_check', $propertyToTest->getColumnName());
    }

    public function testNullableOverwriteFalse()
    {
        $propertyToTest = $this->getReflectionProperty('nullableFalse');

        $this->assertEquals(false, $propertyToTest->isNullable());
    }

    public function testNullableOverwriteTrue()
    {
        $propertyToTest = $this->getReflectionProperty('nullableTrue');

        $this->assertEquals(true, $propertyToTest->isNullable());
    }

    public function testNullableFalse()
    {
        $propertyToTest = $this->getReflectionProperty('nullableBasedOnFieldFalse');

        $this->assertEquals(false, $propertyToTest->isNullable());
    }

    public function testNullableTrue()
    {
        $propertyToTest = $this->getReflectionProperty('nullableBasedOnFieldTrue');

        $this->assertEquals(true, $propertyToTest->isNullable());
    }

    public function testDefalutValueNull()
    {
        $propertyToTest = $this->getReflectionProperty('defaultValueBasedOnFieldNull');

        $this->assertNull($propertyToTest->getDefaultValue());
    }

    public function testDefalutValueOverwritten()
    {
        $propertyToTest = $this->getReflectionProperty('defaultValueOverwriteCheck');

        $this->assertEquals('test', $propertyToTest->getDefaultValue());
    }

    private function getReflectionProperty(string $name): ReflectionProperty
    {
        $reflectionClass = new ReflectionClass(static::class);
        $properties = $reflectionClass->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === $name) {
                /** @var Property $attribute */
                $attribute = $property->getAttributes(Property::class);
                $propertyToTest = new ReflectionProperty($attribute[0]->newInstance(), $property);

                break;
            }
        }
        if (!$propertyToTest) {
            throw new Exception('no attribute');
        }

        return $propertyToTest;
    }
}
