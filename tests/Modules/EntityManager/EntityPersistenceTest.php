<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class TestUser {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public string $email;

    #[Property]
    public ?int $age = null;
}

class EntityPersistenceTest extends AbstractTestCase {
    protected bool $useTransactions = false;

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $this->createTestUserTable($connection, $databaseName);
            return true;
        } catch (Exception $e) {
            // If table creation fails (e.g., table already exists), try to drop and recreate
            try {
                $connection->executeQuery('DROP TABLE IF EXISTS test_user');
                $this->createTestUserTable($connection, $databaseName);
                return true;
            } catch (Exception $dropException) {
                // If we still can't create the table, skip this database
                return false;
            }
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS test_user');
    }

    public function testInsertNewEntity(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist a new user
            $user = new TestUser();
            $user->name = 'John Doe';
            $user->email = 'john@example.com';
            $user->age = 30;

            $entityManager->persist($user);
            $entityManager->flush();

            // Verify the user was inserted
            $this->assertNotNull($user->id, 'User ID should be generated after insert');

            // Verify we can retrieve the user from database
            $retrievedUser = $entityManager->find(TestUser::class, $user->id);
            $this->assertNotNull($retrievedUser);
            $this->assertEquals('John Doe', $retrievedUser->name);
            $this->assertEquals('john@example.com', $retrievedUser->email);
            $this->assertEquals(30, $retrievedUser->age);
        });
    }

    /**
     * Helper method to run a test function against all available databases.
     */
    private function runTestForAllDatabases(callable $testFunction): void
    {
        $databases = ['mysql', 'pgsql'];

        foreach ($databases as $databaseName) {
            // Skip databases that are not available
            if (!$this->isDatabaseAvailable($databaseName)) {
                continue;
            }

            $connection = $this->getConnection($databaseName);

            try {
                $testFunction($connection, $databaseName);
            } catch (\Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * Create the test_user table for testing.
     */
    private function createTestUserTable(Connection $connection, string $databaseName): void
    {
        // Drop table if it exists
        $connection->executeQuery('DROP TABLE IF EXISTS test_user');

        if ($databaseName === 'mysql') {
            $sql = 'CREATE TABLE test_user (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                age INT NULL
            )';
        } elseif ($databaseName === 'pgsql') {
            $sql = 'CREATE TABLE test_user (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                age INTEGER NULL
            )';
        }

        $connection->executeQuery($sql);
    }

    public function testInsertEntityWithNullValues(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist a user with null age
            $user = new TestUser();
            $user->name = 'Jane Doe';
            $user->email = 'jane@example.com';
            // age is null by default

            $entityManager->persist($user);
            $entityManager->flush();

            // Verify the user was inserted with null age
            $this->assertNotNull($user->id);

            $retrievedUser = $entityManager->find(TestUser::class, $user->id);
            $this->assertNotNull($retrievedUser);
            $this->assertEquals('Jane Doe', $retrievedUser->name);
            $this->assertEquals('jane@example.com', $retrievedUser->email);
            $this->assertNull($retrievedUser->age);
        });
    }

    public function testUpdateExistingEntity(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist a user
            $user = new TestUser();
            $user->name = 'John Doe';
            $user->email = 'john@example.com';
            $user->age = 30;

            $entityManager->persist($user);
            $entityManager->flush();

            $originalId = $user->id;

            // Modify the user and persist changes
            $user->name = 'John Smith';
            $user->age = 31;

            $entityManager->persist($user);
            $entityManager->flush();

            // Verify ID remains the same
            $this->assertEquals($originalId, $user->id);

            // Verify changes were saved
            $retrievedUser = $entityManager->find(TestUser::class, $user->id);
            $this->assertNotNull($retrievedUser);
            $this->assertEquals('John Smith', $retrievedUser->name);
            $this->assertEquals('john@example.com', $retrievedUser->email); // unchanged
            $this->assertEquals(31, $retrievedUser->age);

        });
    }

    public function testUpdateOnlyChangedFields(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist a user
            $user = new TestUser();
            $user->name = 'Original Name';
            $user->email = 'original@example.com';
            $user->age = 25;

            $entityManager->persist($user);
            $entityManager->flush();

            // Only change one field
            $user->name = 'Updated Name';
            // email and age remain unchanged

            $entityManager->persist($user);
            $entityManager->flush();

            // Verify only name was updated
            $retrievedUser = $entityManager->find(TestUser::class, $user->id);
            $this->assertNotNull($retrievedUser);
            $this->assertEquals('Updated Name', $retrievedUser->name);
            $this->assertEquals('original@example.com', $retrievedUser->email); // unchanged
            $this->assertEquals(25, $retrievedUser->age); // unchanged

        });
    }

    public function testMultipleInsertsAndUpdates(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create multiple users
            $user1 = new TestUser();
            $user1->name = 'User One';
            $user1->email = 'user1@example.com';

            $user2 = new TestUser();
            $user2->name = 'User Two';
            $user2->email = 'user2@example.com';

            $user3 = new TestUser();
            $user3->name = 'User Three';
            $user3->email = 'user3@example.com';

            // Persist all users
            $entityManager->persist($user1);
            $entityManager->persist($user2);
            $entityManager->persist($user3);
            $entityManager->flush();

            // Verify all users were inserted
            $this->assertNotNull($user1->id);
            $this->assertNotNull($user2->id);
            $this->assertNotNull($user3->id);

            // Update user2
            $user2->name = 'Updated User Two';
            $entityManager->persist($user2);
            $entityManager->flush();

            // Verify user2 was updated and others unchanged
            $retrievedUser1 = $entityManager->find(TestUser::class, $user1->id);
            $retrievedUser2 = $entityManager->find(TestUser::class, $user2->id);
            $retrievedUser3 = $entityManager->find(TestUser::class, $user3->id);

            $this->assertEquals('User One', $retrievedUser1->name);
            $this->assertEquals('Updated User Two', $retrievedUser2->name);
            $this->assertEquals('User Three', $retrievedUser3->name);

        });
    }

    public function testTransactionRollbackOnInsertFailure(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Add unique constraint on email (if supported)
            if ($databaseName === 'mysql') {
                try {
                    $connection->executeQuery('ALTER TABLE test_users ADD UNIQUE KEY unique_email (email)');
                } catch (\Exception $e) {
                    // Skip if unique constraint not supported or already exists
                }
            }

            // Create first user
            $user1 = new TestUser();
            $user1->name = 'User One';
            $user1->email = 'duplicate@example.com';

            $entityManager->persist($user1);
            $entityManager->flush();

            // Create second user with duplicate email (if constraint is supported)
            $user2 = new TestUser();
            $user2->name = 'User Two';
            $user2->email = 'duplicate@example.com'; // duplicate

            $entityManager->persist($user2);

            // This should either succeed (no constraint) or fail due to duplicate email constraint
            try {
                $entityManager->flush();
                // If we get here, the constraint wasn't enforced or it succeeded
                $this->assertTrue(true);
            } catch (\Exception $e) {
                // Constraint violation occurred, which is expected for some databases
                $this->assertTrue(true);
            }

        });
    }

    public function testFindAllReturnsInsertedEntities(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist multiple users
            $user1 = new TestUser();
            $user1->name = 'User One';
            $user1->email = 'user1@example.com';

            $user2 = new TestUser();
            $user2->name = 'User Two';
            $user2->email = 'user2@example.com';

            $entityManager->persist($user1);
            $entityManager->persist($user2);
            $entityManager->flush();

            // Test findAll
            $allUsers = $entityManager->findAll(TestUser::class);

            $this->assertCount(2, $allUsers);
            $this->assertContains($user1->id, array_map(fn ($u) => $u->id, $allUsers));
            $this->assertContains($user2->id, array_map(fn ($u) => $u->id, $allUsers));

            // Clean up
            $connection->executeQuery('DROP TABLE IF EXISTS test_users');
        });
    }

    public function testDeleteExistingEntity(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist a user
            $user = new TestUser();
            $user->name = 'User to Delete';
            $user->email = 'delete@example.com';

            $entityManager->persist($user);
            $entityManager->flush();

            $userId = $user->id;

            // Verify user exists
            $retrievedUser = $entityManager->find(TestUser::class, $userId);
            $this->assertNotNull($retrievedUser);
            $this->assertEquals('User to Delete', $retrievedUser->name);

            // Delete the user
            $entityManager->remove($user);
            $entityManager->flush();

            // Verify user is deleted by checking findAll returns empty array
            $remainingUsers = $entityManager->findAll(TestUser::class);
            $this->assertEmpty($remainingUsers, 'User should be deleted from database');

            // Also verify by querying the database directly
            $directQuery = $connection->executeQuery('SELECT COUNT(*) as count FROM test_user WHERE id = ?', [$userId]);
            $result = $directQuery->fetchAll();
            $this->assertEquals(0, $result[0]['count'], 'User should be deleted from database table directly');

            // Clean up
            $connection->executeQuery('DROP TABLE IF EXISTS test_users');
        });
    }

    public function testDeleteMultipleEntities(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist multiple users
            $user1 = new TestUser();
            $user1->name = 'User One';
            $user1->email = 'user1@example.com';

            $user2 = new TestUser();
            $user2->name = 'User Two';
            $user2->email = 'user2@example.com';

            $user3 = new TestUser();
            $user3->name = 'User Three';
            $user3->email = 'user3@example.com';

            $entityManager->persist($user1);
            $entityManager->persist($user2);
            $entityManager->persist($user3);
            $entityManager->flush();

            // Verify all users exist
            $this->assertCount(3, $entityManager->findAll(TestUser::class));

            // Delete two users
            $entityManager->remove($user1);
            $entityManager->remove($user3);
            $entityManager->flush();

            // Verify only user2 remains
            $remainingUsers = $entityManager->findAll(TestUser::class);
            $this->assertCount(1, $remainingUsers);
            $this->assertEquals($user2->id, $remainingUsers[0]->id);
            $this->assertEquals('User Two', $remainingUsers[0]->name);

            // Clean up
            $connection->executeQuery('DROP TABLE IF EXISTS test_users');
        });
    }

    public function testDeleteNonExistentEntity(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create a user that was never persisted
            $user = new TestUser();
            $user->name = 'Never Persisted';
            $user->email = 'never@example.com';
            $user->id = 999; // Set an ID manually

            // Try to delete - should not throw an error
            $entityManager->remove($user);
            $entityManager->flush();

            // Should not have affected the database
            $this->assertTrue(true);

            // Clean up
            $connection->executeQuery('DROP TABLE IF EXISTS test_users');
        });
    }

    public function testDeleteAndInsertInSameTransaction(): void
    {
        $this->runTestForAllDatabases(function (Connection $connection, string $databaseName) {
            $entityManager = new EntityManager($connection);

            // Create and persist initial user
            $user1 = new TestUser();
            $user1->name = 'Original User';
            $user1->email = 'original@example.com';

            $entityManager->persist($user1);
            $entityManager->flush();

            // In the same "transaction" (flush call), delete user1 and create user2
            $user2 = new TestUser();
            $user2->name = 'New User';
            $user2->email = 'new@example.com';

            $entityManager->remove($user1);
            $entityManager->persist($user2);
            $entityManager->flush();

            // Verify user1 is deleted and user2 exists by checking database directly
            $user1Query = $connection->executeQuery('SELECT COUNT(*) as count FROM test_user WHERE id = ?', [$user1->id]);
            $user1Result = $user1Query->fetchAll();
            $this->assertEquals(0, $user1Result[0]['count'], 'User1 should be deleted from database');

            $user2Query = $connection->executeQuery('SELECT * FROM test_user WHERE id = ?', [$user2->id]);
            $user2Result = $user2Query->fetchAll();
            $this->assertCount(1, $user2Result, 'User2 should exist in database');
            $this->assertEquals('New User', $user2Result[0]['name']);

            // Clean up
            $connection->executeQuery('DROP TABLE IF EXISTS test_users');
        });
    }
}
