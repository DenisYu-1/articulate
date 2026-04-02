<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\StringUtils;
use PHPUnit\Framework\TestCase;

class StringUtilsTest extends TestCase {
    public function testSnakeCaseSimple(): void
    {
        $this->assertSame('foo_bar', StringUtils::snakeCase('FooBar'));
    }

    public function testSnakeCaseAlreadySnake(): void
    {
        $this->assertSame('foo_bar', StringUtils::snakeCase('foo_bar'));
    }

    public function testSnakeCaseSingleWord(): void
    {
        $this->assertSame('foo', StringUtils::snakeCase('Foo'));
    }

    public function testSnakeCaseWithConsecutiveCaps(): void
    {
        $this->assertSame('h_t_t_p_client', StringUtils::snakeCase('HTTPClient'));
    }
}
