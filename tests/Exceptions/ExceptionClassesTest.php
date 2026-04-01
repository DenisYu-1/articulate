<?php

namespace Articulate\Tests\Exceptions;

use Articulate\Exceptions\CursorPaginationException;
use Articulate\Exceptions\DatabaseSchemaException;
use Articulate\Exceptions\EmptyPropertiesListException;
use Articulate\Exceptions\EntityNotFoundException;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Exceptions\UpdateConflictException;
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

    public function testEmptyPropertiesListExceptionCanBeInstantiated(): void
    {
        $tableName = 'users';
        $exception = new EmptyPropertiesListException($tableName);

        $this->assertInstanceOf(EmptyPropertiesListException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('No columns specified for users', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testEmptyPropertiesListExceptionWithCode(): void
    {
        $tableName = 'posts';
        $code = 100;
        $exception = new EmptyPropertiesListException($tableName, $code);

        $this->assertEquals('No columns specified for posts', $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testEmptyPropertiesListExceptionWithPreviousException(): void
    {
        $tableName = 'comments';
        $previous = new InvalidArgumentException('Invalid table');
        $exception = new EmptyPropertiesListException($tableName, 0, $previous);

        $this->assertEquals('No columns specified for comments', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testEntityNotFoundExceptionCanBeInstantiated(): void
    {
        $exception = new EntityNotFoundException('Entity not found');

        $this->assertInstanceOf(EntityNotFoundException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('Entity not found', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testEntityNotFoundExceptionInvalidClassFactory(): void
    {
        $exception = EntityNotFoundException::invalidClass('Test\\Entity\\User');

        $this->assertEquals("Entity class 'Test\\Entity\\User' is not a valid entity", $exception->getMessage());
    }

    public function testEntityNotFoundExceptionNotFoundFactory(): void
    {
        $exception = EntityNotFoundException::notFound('App\\Entity\\User', 42);

        $this->assertEquals('Entity App\\Entity\\User with ID 42 not found in database', $exception->getMessage());
    }

    public function testEntityNotFoundExceptionWithCode(): void
    {
        $code = 200;
        $exception = new EntityNotFoundException('Not found', $code);

        $this->assertEquals('Not found', $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testEntityNotFoundExceptionWithPreviousException(): void
    {
        $previous = new ReflectionException('Class not found');
        $exception = new EntityNotFoundException('Entity missing', 0, $previous);

        $this->assertEquals('Entity missing', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCursorPaginationExceptionCanBeInstantiated(): void
    {
        $message = 'Invalid cursor value';
        $exception = new CursorPaginationException($message);

        $this->assertInstanceOf(CursorPaginationException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testCursorPaginationExceptionWithCodeAndPrevious(): void
    {
        $previous = new RuntimeException('Decode failed');
        $exception = new CursorPaginationException('Bad cursor', 400, $previous);

        $this->assertEquals('Bad cursor', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testTransactionRequiredExceptionHasDefaultMessage(): void
    {
        $exception = new TransactionRequiredException();

        $this->assertInstanceOf(TransactionRequiredException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('A transaction is required for this operation', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function testTransactionRequiredExceptionAcceptsCustomMessage(): void
    {
        $exception = new TransactionRequiredException('lock() requires an active transaction');

        $this->assertEquals('lock() requires an active transaction', $exception->getMessage());
    }

    public function testTransactionRequiredExceptionWithCodeAndPrevious(): void
    {
        $previous = new RuntimeException('No active transaction');
        $exception = new TransactionRequiredException('Transaction required', 503, $previous);

        $this->assertEquals('Transaction required', $exception->getMessage());
        $this->assertEquals(503, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testUpdateConflictExceptionIsArticulateException(): void
    {
        $exception = new UpdateConflictException('Conflicting updates detected');

        $this->assertInstanceOf(UpdateConflictException::class, $exception);
        $this->assertInstanceOf(\Articulate\Exceptions\ArticulateException::class, $exception);
        $this->assertEquals('Conflicting updates detected', $exception->getMessage());
    }

    public function testUpdateConflictExceptionCanBeThrown(): void
    {
        $this->expectException(UpdateConflictException::class);
        $this->expectExceptionMessage('Duplicate update for the same row');

        throw new UpdateConflictException('Duplicate update for the same row');
    }
}
