<?php

namespace Articulate\Tests;

use Articulate\Connection;

class ConnectionTest extends AbstractTestCase {
    public function testCommitWithoutTransactionDoesNotThrow(): void
    {
        $connection = ConnectionPool::getInstance()->getMysqlConnection();
        $this->assertNotNull($connection);

        if ($connection->inTransaction()) {
            $connection->rollbackTransaction();
        }

        $connection->commit();
        $this->assertFalse($connection->inTransaction());
    }

    public function testBeginTransactionTwiceDoesNotNest(): void
    {
        $connection = ConnectionPool::getInstance()->getMysqlConnection();
        $this->assertNotNull($connection);

        if ($connection->inTransaction()) {
            $connection->rollbackTransaction();
        }

        $connection->beginTransaction();
        $connection->beginTransaction();

        $this->assertTrue($connection->inTransaction());

        $connection->commit();
        $this->assertFalse($connection->inTransaction());
    }

    public function testRollbackWithoutTransactionDoesNotThrow(): void
    {
        $connection = ConnectionPool::getInstance()->getMysqlConnection();
        $this->assertNotNull($connection);

        if ($connection->inTransaction()) {
            $connection->rollbackTransaction();
        }

        $connection->rollbackTransaction();
        $this->assertFalse($connection->inTransaction());
    }

    public function testInTransactionReturnsCorrectState(): void
    {
        $connection = ConnectionPool::getInstance()->getMysqlConnection();
        $this->assertNotNull($connection);

        if ($connection->inTransaction()) {
            $connection->rollbackTransaction();
        }

        $this->assertFalse($connection->inTransaction());

        $connection->beginTransaction();
        $this->assertTrue($connection->inTransaction());

        $connection->commit();
        $this->assertFalse($connection->inTransaction());
    }

    public function testNormalizeBoolParameters(): void
    {
        $connection = ConnectionPool::getInstance()->getMysqlConnection();
        $this->assertNotNull($connection);

        $connection->executeQuery('CREATE TEMPORARY TABLE __test_bool (id INT, active TINYINT(1))');
        $connection->executeQuery(
            'INSERT INTO __test_bool (id, active) VALUES (:id, :active)',
            ['id' => 1, 'active' => true],
        );

        $result = $connection->executeQuery(
            'SELECT active FROM __test_bool WHERE id = :id AND active = :active',
            ['id' => 1, 'active' => true],
        );

        $row = $result->fetch();
        $this->assertNotFalse($row);
        $this->assertEquals(1, $row['active']);
    }

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        return true;
    }
}
