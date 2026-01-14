<?php

namespace Articulate\Tests\Exceptions;

use Articulate\Exceptions\DatabaseSchemaException;
use Articulate\Exceptions\EmptyPropertiesList;
use Articulate\Exceptions\EntityNotFoundException;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;

class ExceptionClassesTest extends TestCase {
    public function testDatabaseSchemaExceptionCanBeInstantiated(): void
    {
        $message = 'Database schema error occurred';
        $exception = new DatabaseSchemaException($message);

        $this->assertInstanceOf(DatabaseSchemaException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testDatabaseSchemaExceptionWithCode(): void
    {
        $message = 'Schema validation failed';
        $code = 42;
        $exception = new DatabaseSchemaException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testDatabaseSchemaExceptionWithPreviousException(): void
    {
        $message = 'Schema error';
        $previous = new RuntimeException('Previous error');
        $exception = new DatabaseSchemaException($message, 0, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testEmptyPropertiesListCanBeInstantiated(): void
    {
        $tableName = 'users';
        $exception = new EmptyPropertiesList($tableName);

        $this->assertInstanceOf(EmptyPropertiesList::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('No columns specified for users', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testEmptyPropertiesListWithCode(): void
    {
        $tableName = 'posts';
        $code = 100;
        $exception = new EmptyPropertiesList($tableName, $code);

        $this->assertEquals('No columns specified for posts', $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testEmptyPropertiesListWithPreviousException(): void
    {
        $tableName = 'comments';
        $previous = new InvalidArgumentException('Invalid table');
        $exception = new EmptyPropertiesList($tableName, 0, $previous);

        $this->assertEquals('No columns specified for comments', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testEntityNotFoundExceptionCanBeInstantiated(): void
    {
        $className = 'Test\\Entity\\User';
        $exception = new EntityNotFoundException($className);

        $this->assertInstanceOf(EntityNotFoundException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals("Entity class 'Test\\Entity\\User' is not a valid entity", $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testEntityNotFoundExceptionWithCode(): void
    {
        $className = 'Invalid\\Entity';
        $code = 200;
        $exception = new EntityNotFoundException($className, $code);

        $this->assertEquals("Entity class 'Invalid\\Entity' is not a valid entity", $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testEntityNotFoundExceptionWithPreviousException(): void
    {
        $className = 'Missing\\Entity';
        $previous = new ReflectionException('Class not found');
        $exception = new EntityNotFoundException($className, 0, $previous);

        $this->assertEquals("Entity class 'Missing\\Entity' is not a valid entity", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
