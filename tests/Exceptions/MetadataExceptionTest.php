<?php

namespace Articulate\Tests\Exceptions;

use Articulate\Exceptions\MetadataException;
use Articulate\Tests\AbstractTestCase;

class MetadataExceptionTest extends AbstractTestCase {
    public function testCanBeInstantiatedWithMessage(): void
    {
        $message = 'Test metadata exception message';
        $exception = new MetadataException($message);

        $this->assertInstanceOf(MetadataException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testCanBeInstantiatedWithCode(): void
    {
        $message = 'Test message';
        $code = 42;
        $exception = new MetadataException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testCanBeInstantiatedWithPreviousException(): void
    {
        $message = 'Test message';
        $previous = new \RuntimeException('Previous exception');
        $exception = new MetadataException($message, 0, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}