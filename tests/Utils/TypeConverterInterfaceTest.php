<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\TypeConverterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test the TypeConverterInterface contract.
 * Since it's an interface, we test it through a mock implementation.
 */
class TypeConverterInterfaceTest extends TestCase {
    public function testInterfaceContract(): void
    {
        $converter = $this->createMock(TypeConverterInterface::class);

        // Test that the interface requires the correct methods
        $this->assertTrue(method_exists($converter, 'convertToDatabase'));
        $this->assertTrue(method_exists($converter, 'convertToPHP'));

        // Test that methods can be called with mixed parameters
        $converter->expects($this->once())
                  ->method('convertToDatabase')
                  ->with('test_value')
                  ->willReturn('converted_value');

        $converter->expects($this->once())
                  ->method('convertToPHP')
                  ->with('db_value')
                  ->willReturn('php_value');

        $this->assertSame('converted_value', $converter->convertToDatabase('test_value'));
        $this->assertSame('php_value', $converter->convertToPHP('db_value'));
    }
}
