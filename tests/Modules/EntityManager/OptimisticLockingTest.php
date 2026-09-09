<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Lifecycle\PostUpdate;
use Articulate\Attributes\Property;
use Articulate\Attributes\SoftDeleteable;
use Articulate\Attributes\Version;
use Articulate\Attributes\VersionAware;
use Articulate\Connection;
use Articulate\Exceptions\OptimisticLockException;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity(tableName: 'ol_accounts')]
class OptimisticLockAccount {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name = '';

    #[Property]
    #[Version]
    public int $version = 0;
}

#[Entity(tableName: 'ol_shared')]
class OptimisticLockCheckedSibling {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $status = '';

    #[Property]
    #[Version]
    public int $version = 0;
}

#[Entity(tableName: 'ol_accounts')]
class OptimisticLockPostUpdateAccount {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name = '';

    #[Property]
    #[Version]
    public int $version = 0;

    public ?int $versionSeenInPostUpdate = null;

    public bool $throwFromPostUpdate = false;

    #[PostUpdate]
    public function capturePostUpdateVersion(): void
    {
        $this->versionSeenInPostUpdate = $this->version;

        if ($this->throwFromPostUpdate) {
            throw new \RuntimeException('boom from postUpdate');
        }
    }
}

#[Entity(tableName: 'ol_shared')]
class OptimisticLockCheckedSiblingTwo {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $title = '';

    #[Property]
    #[Version]
    public int $version = 0;
}

#[Entity(tableName: 'ol_shared')]
#[VersionAware(['version'])]
class OptimisticLockAwareSibling {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $title = '';
}

#[Entity(tableName: 'ol_shared')]
#[VersionAware(['version'])]
class OptimisticLockAwareSiblingTwo {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $note = '';
}

#[Entity(tableName: 'ol_revisioned')]
class OptimisticLockRevisionedAccount {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name = '';

    #[Version(name: 'lock_version')]
    public int $revisionCount = 0;
}

#[Entity(tableName: 'ol_soft_delete')]
#[SoftDeleteable]
class OptimisticLockSoftDeleteAccount {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name = '';

    #[Property]
    #[Version]
    public int $version = 0;

    #[Property(nullable: true)]
    public ?\DateTimeImmutable $deletedAt = null;
}

class OptimisticLockingTest extends DatabaseTestCase {
    private function createAccountsTable(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS ol_accounts' . ($databaseName === 'pgsql' ? ' CASCADE' : ''));
        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE ol_accounts (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, version INT NOT NULL DEFAULT 0)',
            'pgsql' => 'CREATE TABLE ol_accounts (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, version INT NOT NULL DEFAULT 0)',
            default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
        };
        $connection->executeQuery($sql);
    }

    private function createSharedTable(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS ol_shared' . ($databaseName === 'pgsql' ? ' CASCADE' : ''));
        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE ol_shared (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(255) NOT NULL DEFAULT \'\', title VARCHAR(255) NOT NULL DEFAULT \'\', note VARCHAR(255) NOT NULL DEFAULT \'\', version INT NOT NULL DEFAULT 0)',
            'pgsql' => 'CREATE TABLE ol_shared (id SERIAL PRIMARY KEY, status VARCHAR(255) NOT NULL DEFAULT \'\', title VARCHAR(255) NOT NULL DEFAULT \'\', note VARCHAR(255) NOT NULL DEFAULT \'\', version INT NOT NULL DEFAULT 0)',
            default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
        };
        $connection->executeQuery($sql);
    }

    private function createRevisionedTable(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS ol_revisioned' . ($databaseName === 'pgsql' ? ' CASCADE' : ''));
        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE ol_revisioned (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, lock_version INT NOT NULL DEFAULT 0)',
            'pgsql' => 'CREATE TABLE ol_revisioned (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, lock_version INT NOT NULL DEFAULT 0)',
            default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
        };
        $connection->executeQuery($sql);
    }

    #[DataProvider('databaseProvider')]
    public function testBareVersionWithExplicitColumnNamePersistsAndReads(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createRevisionedTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockRevisionedAccount();
        $account->name = 'Ada';
        $em->persist($account);
        $em->flush();

        $this->assertSame(0, $account->revisionCount);

        $account->name = 'Ada Updated';
        $em->persist($account);
        $em->flush();

        $this->assertSame(1, $account->revisionCount);

        $row = $connection->executeQuery('SELECT lock_version FROM ol_revisioned WHERE id = ?', [$account->id])->fetch();
        $this->assertSame(1, (int) $row['lock_version']);

        $reloaded = (new EntityManager($connection))->find(OptimisticLockRevisionedAccount::class, $account->id);
        $this->assertNotNull($reloaded);
        $this->assertSame(1, $reloaded->revisionCount);
    }

    private function createSoftDeleteTable(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS ol_soft_delete' . ($databaseName === 'pgsql' ? ' CASCADE' : ''));
        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE ol_soft_delete (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, version INT NOT NULL DEFAULT 0, deleted_at DATETIME NULL)',
            'pgsql' => 'CREATE TABLE ol_soft_delete (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, version INT NOT NULL DEFAULT 0, deleted_at TIMESTAMP NULL)',
            default => throw new \InvalidArgumentException("Unsupported database: {$databaseName}"),
        };
        $connection->executeQuery($sql);
    }

    #[DataProvider('databaseProvider')]
    public function testFreshInsertThenHappyPathUpdateBumpsVersion(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createAccountsTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockAccount();
        $account->name = 'Alice';
        $em->persist($account);
        $em->flush();

        $this->assertSame(0, $account->version);

        $account->name = 'Alice Updated';
        $em->persist($account);
        $em->flush();

        $this->assertSame(1, $account->version);

        $row = $connection->executeQuery('SELECT version FROM ol_accounts WHERE id = ?', [$account->id])->fetch();
        $this->assertSame(1, (int) $row['version']);
    }

    #[DataProvider('databaseProvider')]
    public function testStaleVersionUpdateThrowsOptimisticLockException(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createAccountsTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockAccount();
        $account->name = 'Bob';
        $em->persist($account);
        $em->flush();

        // A concurrent writer bumps the version directly, out of band.
        $connection->executeQuery('UPDATE ol_accounts SET version = version + 1 WHERE id = ?', [$account->id]);

        $account->name = 'Bob Changed';
        $em->persist($account);

        $this->expectException(OptimisticLockException::class);
        $em->flush();
    }

    #[DataProvider('databaseProvider')]
    public function testTwoFlushesInSameRequestBothSucceed(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createAccountsTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockAccount();
        $account->name = 'Carol';
        $em->persist($account);
        $em->flush();

        $account->name = 'Carol First Update';
        $em->persist($account);
        $em->flush();
        $this->assertSame(1, $account->version);

        $account->name = 'Carol Second Update';
        $em->persist($account);
        $em->flush();
        $this->assertSame(2, $account->version);
    }

    #[DataProvider('databaseProvider')]
    public function testVersionAwareSiblingBumpsColumnWithoutCheckingIt(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createSharedTable($connection, $databaseName);

        $checkedEm = new EntityManager($connection);
        $checked = new OptimisticLockCheckedSibling();
        $checked->status = 'initial';
        $checkedEm->persist($checked);
        $checkedEm->flush();

        $awareEm = new EntityManager($connection);
        $aware = $awareEm->find(OptimisticLockAwareSibling::class, $checked->id);
        $aware->title = 'Changed by aware sibling';
        $awareEm->persist($aware);
        $awareEm->flush();

        $row = $connection->executeQuery('SELECT version FROM ol_shared WHERE id = ?', [$checked->id])->fetch();
        $this->assertSame(1, (int) $row['version'], 'VersionAware sibling must bump the shared column');

        // The checked sibling still holds the stale in-memory version (0) — its next
        // flush must now detect the lost update caused by the aware sibling's write.
        $checked->status = 'checked writer update';
        $checkedEm->persist($checked);

        $this->expectException(OptimisticLockException::class);
        $checkedEm->flush();
    }

    #[DataProvider('databaseProvider')]
    public function testTwoVersionAwareSiblingsCombinedBumpColumnOnceNotTwice(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createSharedTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $seed = new OptimisticLockCheckedSibling();
        $seed->status = 'seed';
        $em->persist($seed);
        $em->flush();
        $em->clear();

        $unitOfWorkOne = $em->createUnitOfWork();
        $em->setActiveUnitOfWork($unitOfWorkOne);
        $awareOne = $em->find(OptimisticLockAwareSibling::class, $seed->id);
        $awareOne->title = 'From aware one';
        $em->persist($awareOne);

        $unitOfWorkTwo = $em->createUnitOfWork();
        $em->setActiveUnitOfWork($unitOfWorkTwo);
        $awareTwo = $em->find(OptimisticLockAwareSiblingTwo::class, $seed->id);
        $awareTwo->note = 'From aware two';
        $em->persist($awareTwo);

        $em->flush();

        $row = $connection->executeQuery('SELECT version FROM ol_shared WHERE id = ?', [$seed->id])->fetch();
        $this->assertSame(1, (int) $row['version'], 'Two siblings bumping the same column in one flush must increment it exactly once');
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteHappyPathBumpsAndChecksVersion(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createSoftDeleteTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockSoftDeleteAccount();
        $account->name = 'Dave';
        $em->persist($account);
        $em->flush();

        $em->remove($account);
        $em->flush();

        $row = $connection->executeQuery('SELECT version, deleted_at FROM ol_soft_delete WHERE id = ?', [$account->id])->fetch();
        $this->assertSame(1, (int) $row['version']);
        $this->assertNotNull($row['deleted_at']);
    }

    #[DataProvider('databaseProvider')]
    public function testSoftDeleteStaleVersionThrowsOptimisticLockException(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createSoftDeleteTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockSoftDeleteAccount();
        $account->name = 'Eve';
        $em->persist($account);
        $em->flush();

        // A concurrent writer bumps the version directly, out of band.
        $connection->executeQuery('UPDATE ol_soft_delete SET version = version + 1 WHERE id = ?', [$account->id]);

        $em->remove($account);

        $this->expectException(OptimisticLockException::class);
        $em->flush();
    }

    #[DataProvider('databaseProvider')]
    public function testFailedFlushDoesNotPoisonInMemoryVersionOfSuccessfullyUpdatedSibling(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createAccountsTable($connection, $databaseName);

        $em = new EntityManager($connection);

        // Persisted first, so its UPDATE runs before the stale one's within the flush.
        $good = new OptimisticLockAccount();
        $good->name = 'Good';
        $stale = new OptimisticLockAccount();
        $stale->name = 'Stale';
        $em->persist($good);
        $em->persist($stale);
        $em->flush();

        // A concurrent writer bumps only the second row out of band.
        $connection->executeQuery('UPDATE ol_accounts SET version = version + 1 WHERE id = ?', [$stale->id]);

        $good->name = 'Good Updated';
        $stale->name = 'Stale Updated';
        $em->persist($good);
        $em->persist($stale);

        try {
            $em->flush();
            $this->fail('Expected OptimisticLockException');
        } catch (OptimisticLockException) {
            // expected: the stale sibling aborts the flush
        }

        // The flush did not complete, so the successfully-updated sibling's in-memory
        // #[Version] property must NOT have been bumped — otherwise every retry sends
        // WHERE version = 1 against a row still at 0 and can never match.
        $this->assertSame(0, $good->version, 'in-memory version must not be bumped by a flush that threw before commit');
    }

    #[DataProvider('databaseProvider')]
    public function testFailedFlushDoesNotPoisonInMemoryVersionOnSoftDeletePath(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createSoftDeleteTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $good = new OptimisticLockSoftDeleteAccount();
        $good->name = 'Good';
        $stale = new OptimisticLockSoftDeleteAccount();
        $stale->name = 'Stale';
        $em->persist($good);
        $em->persist($stale);
        $em->flush();

        $connection->executeQuery('UPDATE ol_soft_delete SET version = version + 1 WHERE id = ?', [$stale->id]);

        $em->remove($good);
        $em->remove($stale);

        try {
            $em->flush();
            $this->fail('Expected OptimisticLockException');
        } catch (OptimisticLockException) {
            // expected
        }

        $this->assertSame(0, $good->version, 'soft-delete in-memory version must not be bumped by a flush that threw before commit');
    }

    #[DataProvider('databaseProvider')]
    public function testTwoCheckingSiblingsWritingSameRowInOneFlushConflictWithThemselves(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createSharedTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $a = new OptimisticLockCheckedSibling();
        $a->status = 'a';
        $em->persist($a);
        $em->flush();

        $b = $em->find(OptimisticLockCheckedSiblingTwo::class, $a->id);
        $this->assertNotNull($b);

        $a->status = 'a-updated';
        $b->title = 'b-updated';
        $em->persist($a);
        $em->persist($b);

        // Same row, two #[Version]-checking classes, one flush. Checking entities are
        // never merged, so each issues its own UPDATE; the first bumps the shared
        // version column, so the second's WHERE version = <pre-bump value> matches
        // nothing. Writing one row through two checking contexts in a single flush is
        // a self-inflicted lost update and is surfaced, not silently absorbed.
        $this->expectException(OptimisticLockException::class);
        $em->flush();
    }

    #[DataProvider('databaseProvider')]
    public function testPostUpdateCallbackObservesBumpedVersion(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createAccountsTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockPostUpdateAccount();
        $account->name = 'Frank';
        $em->persist($account);
        $em->flush();

        $account->name = 'Frank Updated';
        $em->persist($account);
        $em->flush();

        // The row is at version 1 after the UPDATE; the #[PostUpdate] handler, which
        // runs before commit, must see the same value rather than the stale 0.
        $this->assertSame(1, $account->versionSeenInPostUpdate);
        $this->assertSame(1, $account->version);
    }

    #[DataProvider('databaseProvider')]
    public function testVersionBumpIsRevertedWhenAPostUpdateCallbackThrows(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->createAccountsTable($connection, $databaseName);

        $em = new EntityManager($connection);

        $account = new OptimisticLockPostUpdateAccount();
        $account->name = 'Grace';
        $em->persist($account);
        $em->flush();
        $this->assertSame(0, $account->version);

        $account->name = 'Grace Updated';
        $account->throwFromPostUpdate = true;
        $em->persist($account);

        try {
            $em->flush();
            $this->fail('Expected the post-update callback to abort the flush');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom from postUpdate', $e->getMessage());
        }

        // apply() bumped the property to 1 before the callback fired; the flush then
        // failed, so the catch block's revert() must have restored it to 0 — the
        // value the (rolled-back / uncommitted) row still holds.
        $this->assertSame(1, $account->versionSeenInPostUpdate, 'sanity: the callback did run after apply()');
        $this->assertSame(0, $account->version);
    }
}
