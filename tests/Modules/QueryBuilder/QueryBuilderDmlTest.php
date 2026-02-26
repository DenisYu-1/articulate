<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Table;
use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;

#[Entity]
#[Table('testusers')]
class TestUser {
    #[PrimaryKey]
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property(nullable: true)]
    public ?string $email = null;
}

#[Entity]
class TestProduct {
    #[PrimaryKey]
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public float $price;
}

class QueryBuilderDmlTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    /**
     * @dataProvider databaseProvider
     */
    public function testInsertSingleEntity(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);

        $user = new TestUser();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';

        $id = $this->qb->insert($user)->execute();

        $this->assertIsInt($id);
        $this->assertEquals(1, $id);

        $result = $this->connection->executeQuery('SELECT * FROM testusers WHERE id = ?', [$id])->fetchAll();
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertEquals('john@example.com', $result[0]['email']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testInsertMultiRow(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);

        $user1 = new TestUser();
        $user1->name = 'John Doe';
        $user1->email = 'john@example.com';

        $user2 = new TestUser();
        $user2->name = 'Jane Smith';
        $user2->email = 'jane@example.com';

        $result = $this->qb->insert([$user1, $user2])->execute();

        $this->assertIsInt($result);
        $this->assertEquals(2, $result);

        $results = $this->connection->executeQuery('SELECT * FROM testusers ORDER BY id')->fetchAll();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('Jane Smith', $results[1]['name']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testInsertWithNullValues(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL)',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL)',
        };
        $this->connection->executeQuery($createTableSql);

        $user = new TestUser();
        $user->name = 'John Doe';
        $user->email = null;

        $id = $this->qb->insert($user)->execute();

        $this->assertIsInt($id);

        $result = $this->connection->executeQuery('SELECT * FROM testusers WHERE id = ?', [$id])->fetchAll();
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertNull($result[0]['email']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testUpdateByEntity(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);
        $this->connection->executeQuery('INSERT INTO testusers (id, name, email) VALUES (1, ?, ?)', ['John', 'john@example.com']);

        $user = new TestUser();
        $user->id = 1;
        $user->name = 'John Updated';
        $user->email = 'john.updated@example.com';

        $affected = $this->qb->update($user)->set('name', $user->name)->set('email', $user->email)->execute();

        $this->assertEquals(1, $affected);

        $result = $this->connection->executeQuery('SELECT * FROM testusers WHERE id = 1')->fetchAll();
        $this->assertCount(1, $result);
        $this->assertEquals('John Updated', $result[0]['name']);
        $this->assertEquals('john.updated@example.com', $result[0]['email']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testUpdateWithCustomWhere(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);
        $this->connection->executeQuery('INSERT INTO testusers (id, name, email) VALUES (1, ?, ?), (2, ?, ?)', ['John', 'john@example.com', 'Jane', 'jane@example.com']);

        $affected = $this->qb->update('testusers')->set('email', 'updated@example.com')->where('id IN (?)', [1, 2])->execute();

        $this->assertEquals(2, $affected);

        $results = $this->connection->executeQuery('SELECT * FROM testusers WHERE id IN (1, 2)')->fetchAll();
        foreach ($results as $result) {
            $this->assertEquals('updated@example.com', $result['email']);
        }
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testUpdateWithSetArray(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);
        $this->connection->executeQuery('INSERT INTO testusers (id, name, email) VALUES (1, ?, ?)', ['John', 'john@example.com']);

        $affected = $this->qb->update('testusers')->set(['name' => 'John Updated', 'email' => 'updated@example.com'])->where('id = ?', 1)->execute();

        $this->assertEquals(1, $affected);

        $result = $this->connection->executeQuery('SELECT * FROM testusers WHERE id = 1')->fetchAll();
        $this->assertCount(1, $result);
        $this->assertEquals('John Updated', $result[0]['name']);
        $this->assertEquals('updated@example.com', $result[0]['email']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testDeleteByEntity(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);
        $this->connection->executeQuery('INSERT INTO testusers (id, name, email) VALUES (1, ?, ?), (2, ?, ?)', ['John', 'john@example.com', 'Jane', 'jane@example.com']);

        $user = new TestUser();
        $user->id = 1;

        $affected = $this->qb->delete($user)->execute();

        $this->assertEquals(1, $affected);

        $results = $this->connection->executeQuery('SELECT * FROM testusers')->fetchAll();
        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]['id']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testDeleteWithCustomWhere(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);
        $this->connection->executeQuery('INSERT INTO testusers (id, name, email) VALUES (1, ?, ?), (2, ?, ?)', ['John', 'john@example.com', 'Jane', 'jane@example.com']);

        $affected = $this->qb->delete('testusers')->where('id = ?', 1)->execute();

        $this->assertEquals(1, $affected);

        $results = $this->connection->executeQuery('SELECT * FROM testusers')->fetchAll();
        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]['id']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testInsertReturningPostgreSQL(string $databaseName): void
    {
        if ($databaseName !== 'pgsql') {
            $this->markTestSkipped('RETURNING clause is PostgreSQL-specific');
        }

        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $this->connection->executeQuery('CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');

        $user = new TestUser();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';

        $result = $this->qb->insert($user)->returning('id', 'name')->execute();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertEquals('John Doe', $result[0]['name']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testUpdateReturningPostgreSQL(string $databaseName): void
    {
        if ($databaseName !== 'pgsql') {
            $this->markTestSkipped('RETURNING clause is PostgreSQL-specific');
        }

        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $this->connection->executeQuery('CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO testusers (name, email) VALUES (?, ?)', ['John', 'john@example.com']);

        $result = $this->qb->update('testusers')->set('name', 'John Updated')->where('id = ?', 1)->returning('id', 'name')->execute();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('John Updated', $result[0]['name']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testDeleteReturningPostgreSQL(string $databaseName): void
    {
        if ($databaseName !== 'pgsql') {
            $this->markTestSkipped('RETURNING clause is PostgreSQL-specific');
        }

        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $this->connection->executeQuery('CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO testusers (name, email) VALUES (?, ?)', ['John', 'john@example.com']);

        $result = $this->qb->delete('testusers')->where('id = ?', 1)->returning('id', 'name')->execute();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('John', $result[0]['name']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testInsertWithTableName(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);

        $id = $this->qb->insert('testusers')->values(['name' => 'John', 'email' => 'john@example.com'])->execute();

        $this->assertIsInt($id);
        $this->assertEquals(1, $id);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testResetClearsDmlState(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS testusers');
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE testusers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'pgsql' => 'CREATE TABLE testusers (id SERIAL PRIMARY KEY, name VARCHAR(255))',
        };
        $this->connection->executeQuery($createTableSql);

        $user = new TestUser();
        $user->name = 'John';

        $this->qb->insert($user);
        $this->qb->reset();

        $sql = $this->qb->select('*')->from('testusers')->getSQL();
        $this->assertEquals('SELECT * FROM testusers', $sql);
    }
}
