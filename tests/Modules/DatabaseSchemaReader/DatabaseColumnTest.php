<?php

namespace Articulate\Tests\Modules\DatabaseSchemaReader;

use Articulate\Modules\Database\SchemaReader\DatabaseColumn;
use Articulate\Tests\AbstractTestCase;

class DatabaseColumnTest extends AbstractTestCase
{
    public function testStringTypeWithLength(): void
    {
        $column = new DatabaseColumn('name', 'VARCHAR(255)', false, null);

        $this->assertEquals('name', $column->name);
        $this->assertEquals('VARCHAR', $column->type);
        $this->assertEquals('string', $column->phpType);
        $this->assertEquals(255, $column->length);
        $this->assertFalse($column->isNullable);
        $this->assertNull($column->defaultValue);
    }

    public function testBoolTypeFromTinyInt1(): void
    {
        $column = new DatabaseColumn('is_active', 'TINYINT(1)', false, '0');

        $this->assertEquals('is_active', $column->name);
        $this->assertEquals('TINYINT(1)', $column->type);
        $this->assertEquals('bool', $column->phpType);
        $this->assertEquals(1, $column->length);
        $this->assertFalse($column->isNullable);
        $this->assertEquals('0', $column->defaultValue);
    }

    public function testIntTypeFromTinyInt(): void
    {
        $column = new DatabaseColumn('counter', 'TINYINT(2)', false, null);

        $this->assertEquals('counter', $column->name);
        $this->assertEquals('TINYINT', $column->type);
        $this->assertEquals('int', $column->phpType);
        $this->assertEquals(2, $column->length);
        $this->assertFalse($column->isNullable);
    }

    public function testSimpleTypeWithoutLength(): void
    {
        $column = new DatabaseColumn('id', 'INT', false, null);

        $this->assertEquals('id', $column->name);
        $this->assertEquals('INT', $column->type);
        $this->assertEquals('int', $column->phpType);
        $this->assertNull($column->length);
        $this->assertFalse($column->isNullable);
    }

    public function testNullableColumn(): void
    {
        $column = new DatabaseColumn('description', 'TEXT', true, null);

        $this->assertEquals('description', $column->name);
        $this->assertEquals('TEXT', $column->type);
        $this->assertEquals('mixed', $column->phpType);
        $this->assertNull($column->length);
        $this->assertTrue($column->isNullable);
    }
}


