<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\SoftDeleteable;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;

#[Entity]
#[SoftDeleteable(fieldName: 'deletedAt', columnName: 'deleted_at')]
class SoftDeleteableTestEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[Property]
    public string $name;

    #[Property]
    public ?\DateTime $deletedAt = null;
}

#[Entity]
class NonSoftDeleteableTestEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;
}

#[Entity]
#[SoftDeleteable(fieldName: 'archivedAt', columnName: 'archived_at')]
class CustomSoftDeleteTestEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;
}

class QueryBuilderSoftDeleteTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    private EntityManager $entityManager;

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterAppliedByDefault(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity')
            ->getSQL();

        $this->assertStringContainsString('deleted_at IS NULL', $sql);
        $this->assertEquals('SELECT id, name FROM soft_deleteable_test_entity WHERE deleted_at IS NULL', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterNotAppliedWhenWithDeleted(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity')
            ->withDeleted()
            ->getSQL();

        $this->assertStringNotContainsString('deleted_at IS NULL', $sql);
        $this->assertEquals('SELECT id, name FROM soft_deleteable_test_entity', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterNotAppliedWhenDisabled(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->setSoftDeleteEnabled(false)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity')
            ->getSQL();

        $this->assertStringNotContainsString('deleted_at IS NULL', $sql);
        $this->assertEquals('SELECT id, name FROM soft_deleteable_test_entity', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterWithExistingWhereClause(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity')
            ->where('name = ?', 'test')
            ->getSQL();

        $this->assertStringContainsString('deleted_at IS NULL', $sql);
        $this->assertEquals('SELECT id, name FROM soft_deleteable_test_entity WHERE name = ? AND deleted_at IS NULL', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterNotDuplicated(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity')
            ->where('deleted_at IS NULL')
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM soft_deleteable_test_entity WHERE deleted_at IS NULL', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterNotAppliedForNonSoftDeleteableEntity(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();

        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(NonSoftDeleteableTestEntity::class)
            ->select('id')
            ->from('non_soft_deleteable_test_entity')
            ->getSQL();

        $this->assertStringNotContainsString('deleted_at IS NULL', $sql);
        $this->assertEquals('SELECT id FROM non_soft_deleteable_test_entity', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterNotAppliedForRawSql(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->raw('SELECT * FROM soft_deleteable_test_entity WHERE id = ?', 1)
            ->getSQL();

        $this->assertStringNotContainsString('deleted_at IS NULL', $sql);
        $this->assertEquals('SELECT * FROM soft_deleteable_test_entity WHERE id = ?', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSoftDeleteFilterInSubQuery(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();
        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $subQuery = $this->qb->createSubQueryBuilder()
            ->setEntityClass(SoftDeleteableTestEntity::class)
            ->select('id')
            ->from('soft_deleteable_test_entity');

        $sql = $this->qb
            ->select('*')
            ->from('other_table')
            ->whereIn('id', $subQuery)
            ->getSQL();

        $this->assertStringContainsString('deleted_at IS NULL', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testEntityManagerSoftDeleteFilterApplied(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableTestEntity::class)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity');

        $sql = $qb->getSQL();

        $this->assertStringContainsString('deleted_at IS NULL', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testEntityManagerSoftDeleteCanBeDisabled(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->setSoftDeleteEnabled(false);

        $qb = $this->entityManager->createQueryBuilder(SoftDeleteableTestEntity::class)
            ->select('id', 'name')
            ->from('soft_deleteable_test_entity');

        $sql = $qb->getSQL();

        $this->assertStringNotContainsString('deleted_at IS NULL', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCustomSoftDeleteColumnName(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $metadataRegistry = new EntityMetadataRegistry();

        $this->qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        $sql = $this->qb
            ->setEntityClass(CustomSoftDeleteTestEntity::class)
            ->select('id')
            ->from('custom_soft_delete_test_entity')
            ->getSQL();

        $this->assertStringContainsString('archived_at IS NULL', $sql);
        $this->assertStringNotContainsString('deleted_at IS NULL', $sql);
    }
}
