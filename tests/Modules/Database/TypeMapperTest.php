<?php

namespace Articulate\Tests\Modules\Database;

use Articulate\Modules\Database\MySqlTypeMapper;
use Articulate\Modules\Database\PostgresqlTypeMapper;
use Articulate\Utils\Point;
use PHPUnit\Framework\TestCase;

class TypeMapperTest extends TestCase
{
    public function testMySqlTypeMapperBasicTypes(): void
    {
        $mapper = new MySqlTypeMapper();

        $this->assertSame('INT', $mapper->getDatabaseType('int'));
        $this->assertSame('FLOAT', $mapper->getDatabaseType('float'));
        $this->assertSame('VARCHAR(255)', $mapper->getDatabaseType('string'));
        $this->assertSame('TINYINT(1)', $mapper->getDatabaseType('bool'));
        $this->assertSame('TEXT', $mapper->getDatabaseType('mixed'));
    }

    public function testMySqlTypeMapperDateTimeTypes(): void
    {
        $mapper = new MySqlTypeMapper();

        $this->assertSame('DATETIME', $mapper->getDatabaseType('DateTime'));
        $this->assertSame('DATETIME', $mapper->getDatabaseType('DateTimeImmutable'));
        $this->assertSame('DATETIME', $mapper->getDatabaseType(\DateTimeInterface::class));
    }

    public function testMySqlTypeMapperSpatialTypes(): void
    {
        $mapper = new MySqlTypeMapper();

        $this->assertSame('POINT', $mapper->getDatabaseType(Point::class));
    }

    public function testMySqlTypeMapperTinyIntOneSpecialHandling(): void
    {
        $mapper = new MySqlTypeMapper();

        $this->assertSame('bool', $mapper->getPhpType('TINYINT(1)'));
        $this->assertSame('bool', $mapper->getPhpType('tinyint(1)'));
        $this->assertSame('int', $mapper->getPhpType('TINYINT(2)'));
    }

    public function testPostgresqlTypeMapperBasicTypes(): void
    {
        $mapper = new PostgresqlTypeMapper();

        $this->assertSame('INTEGER', $mapper->getDatabaseType('int'));
        $this->assertSame('DOUBLE PRECISION', $mapper->getDatabaseType('float'));
        $this->assertSame('VARCHAR(255)', $mapper->getDatabaseType('string'));
        $this->assertSame('BOOLEAN', $mapper->getDatabaseType('bool'));
        $this->assertSame('TEXT', $mapper->getDatabaseType('mixed'));
    }

    public function testPostgresqlTypeMapperDateTimeTypes(): void
    {
        $mapper = new PostgresqlTypeMapper();

        $this->assertSame('TIMESTAMP', $mapper->getDatabaseType('DateTime'));
        $this->assertSame('TIMESTAMP', $mapper->getDatabaseType('DateTimeImmutable'));
        $this->assertSame('TIMESTAMP', $mapper->getDatabaseType(\DateTimeInterface::class));
    }

    public function testPostgresqlTypeMapperPostgresqlSpecificTypes(): void
    {
        $mapper = new PostgresqlTypeMapper();

        $this->assertSame('UUID', $mapper->getDatabaseType('uuid'));
        $this->assertSame('JSONB', $mapper->getDatabaseType('json'));
    }

    public function testPostgresqlTypeMapperBooleanHandling(): void
    {
        $mapper = new PostgresqlTypeMapper();

        $this->assertSame('bool', $mapper->getPhpType('BOOLEAN'));
        $this->assertSame('bool', $mapper->getPhpType('boolean'));
        $this->assertSame('bool', $mapper->getPhpType('BOOL'));
    }

    public function testMySqlTypeMapperNullableTypes(): void
    {
        $mapper = new MySqlTypeMapper();

        $this->assertSame('INT', $mapper->getDatabaseType('?int'));
        $this->assertSame('VARCHAR(255)', $mapper->getDatabaseType('?string'));
        $this->assertSame('TINYINT(1)', $mapper->getDatabaseType('?bool'));
        $this->assertSame('POINT', $mapper->getDatabaseType('?' . Point::class));
    }

    public function testPostgresqlTypeMapperNullableTypes(): void
    {
        $mapper = new PostgresqlTypeMapper();

        $this->assertSame('INTEGER', $mapper->getDatabaseType('?int'));
        $this->assertSame('DOUBLE PRECISION', $mapper->getDatabaseType('?float'));
        $this->assertSame('VARCHAR(255)', $mapper->getDatabaseType('?string'));
        $this->assertSame('BOOLEAN', $mapper->getDatabaseType('?bool'));
    }

    public function testMySqlTypeMapperInheritsFromTypeRegistry(): void
    {
        $mapper = new MySqlTypeMapper();
        $this->assertInstanceOf(\Articulate\Utils\TypeRegistry::class, $mapper);
    }

    public function testPostgresqlTypeMapperInheritsFromTypeRegistry(): void
    {
        $mapper = new PostgresqlTypeMapper();
        $this->assertInstanceOf(\Articulate\Utils\TypeRegistry::class, $mapper);
    }

    public function testMySqlTypeMapperCustomTypeRegistration(): void
    {
        $mapper = new MySqlTypeMapper();

        // Can still register custom types
        $mapper->registerType('custom', 'CUSTOM_TYPE');
        $this->assertSame('CUSTOM_TYPE', $mapper->getDatabaseType('custom'));
    }

    public function testPostgresqlTypeMapperCustomTypeRegistration(): void
    {
        $mapper = new PostgresqlTypeMapper();

        // Can still register custom types
        $mapper->registerType('custom', 'CUSTOM_TYPE');
        $this->assertSame('CUSTOM_TYPE', $mapper->getDatabaseType('custom'));
    }

    public function testMySqlTypeMapperUnknownTypesFallBack(): void
    {
        $mapper = new MySqlTypeMapper();

        // Unknown types should fall back to themselves
        $this->assertSame('UNKNOWN_TYPE', $mapper->getDatabaseType('UNKNOWN_TYPE'));
    }

    public function testPostgresqlTypeMapperUnknownTypesFallBack(): void
    {
        $mapper = new PostgresqlTypeMapper();

        // Unknown types should fall back to themselves
        $this->assertSame('UNKNOWN_TYPE', $mapper->getDatabaseType('UNKNOWN_TYPE'));
    }
}