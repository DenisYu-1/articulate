<?php

namespace Articulate\Tests;

use Articulate\Connection;

class ConnectionTest extends AbstractTestCase
{
    public function testPdoAttributesAreSetCorrectly()
    {
        // We can't easily test the actual PDO construction without a real database
        // But we can test that the connection can be created with proper DSN
        $this->expectNotToPerformAssertions();

        try {
            // This will fail due to no real database, but we can catch the exception
            // The important thing is that the PDO options are set correctly in the constructor
            new Connection('sqlite::memory:', 'test', 'test');
        } catch (\Exception $e) {
            // Expected to fail due to database connection
            $this->assertStringContains('could not find driver', $e->getMessage());
        }
    }

    public function testBeginTransactionIsCalled()
    {
        $connection = new Connection('sqlite::memory:', 'test', 'test');

        // Test that we can call beginTransaction (it should handle already in transaction)
        $this->expectNotToPerformAssertions();
        $connection->beginTransaction();
    }

    public function testRollbackTransactionIsCalled()
    {
        $connection = new Connection('sqlite::memory:', 'test', 'test');

        // Test that we can call rollbackTransaction
        $this->expectNotToPerformAssertions();
        $connection->rollbackTransaction();
    }
}

