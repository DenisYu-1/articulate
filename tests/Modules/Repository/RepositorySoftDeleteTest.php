<?php

namespace Articulate\Tests\Modules\Repository;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\SoftDeleteable;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\Filter\SoftDeleteFilter;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity]
#[SoftDeleteable(fieldName: 'deletedAt', columnName: 'deleted_at')]
class SoftDeleteableProduct {
    #[PrimaryKey]
    #[AutoIncrement]
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public ?\DateTime $deletedAt = null;
}

class ProductRepository extends AbstractRepository {
}

class RepositorySoftDeleteTest extends DatabaseTestCase {
    private Connection $connection;

    private EntityManager $entityManager;

    private ProductRepository $repository;

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        try {
            $this->createProductTable($connection, $databaseName);

            return true;
        } catch (\Exception $e) {
            try {
                $connection->executeQuery('DROP TABLE IF EXISTS soft_deleteable_product');
                $this->createProductTable($connection, $databaseName);

                return true;
            } catch (\Exception $dropException) {
                return false;
            }
        }
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS soft_deleteable_product');
    }

    private function createProductTable(Connection $connection, string $databaseName): void
    {
        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE soft_deleteable_product (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                deleted_at DATETIME NULL
            )',
            'pgsql' => 'CREATE TABLE soft_deleteable_product (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                deleted_at TIMESTAMP NULL
            )',
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}")
        };

        $connection->executeQuery($sql);
    }

    #[DataProvider('databaseProvider')]
    public function testRepositoryFindExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->repository = new ProductRepository($this->entityManager, SoftDeleteableProduct::class);

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_product (id, name, deleted_at) VALUES (?, ?, ?), (?, ?, ?)',
            [1, 'Active Product', null, 2, 'Deleted Product', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $activeProduct = $this->repository->find(1);
        $deletedProduct = $this->repository->find(2);

        $this->assertNotNull($activeProduct);
        $this->assertNull($deletedProduct);
    }

    #[DataProvider('databaseProvider')]
    public function testRepositoryFindAllExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->repository = new ProductRepository($this->entityManager, SoftDeleteableProduct::class);

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_product (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Product 1', null, 'Product 2', null, 'Deleted Product', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $allProducts = $this->repository->findAll();

        $this->assertCount(2, $allProducts);
        $names = array_map(fn ($product) => $product->name, $allProducts);
        $this->assertContains('Product 1', $names);
        $this->assertContains('Product 2', $names);
        $this->assertNotContains('Deleted Product', $names);
    }

    #[DataProvider('databaseProvider')]
    public function testRepositoryFindByExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->repository = new ProductRepository($this->entityManager, SoftDeleteableProduct::class);

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_product (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Widget', null, 'Widget', null, 'Widget', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $products = $this->repository->findBy(['name' => 'Widget']);

        $this->assertCount(2, $products);
    }

    #[DataProvider('databaseProvider')]
    public function testRepositoryCountExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->repository = new ProductRepository($this->entityManager, SoftDeleteableProduct::class);

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_product (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Product 1', null, 'Product 2', null, 'Deleted Product', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $count = $this->repository->count();

        $this->assertEquals(2, $count);
    }

    #[DataProvider('databaseProvider')]
    public function testRepositoryExistsExcludesDeletedEntities(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->repository = new ProductRepository($this->entityManager, SoftDeleteableProduct::class);

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_product (id, name, deleted_at) VALUES (?, ?, ?), (?, ?, ?)',
            [1, 'Active Product', null, 2, 'Deleted Product', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $this->assertTrue($this->repository->exists(1));
        $this->assertFalse($this->repository->exists(2));
    }

    #[DataProvider('databaseProvider')]
    public function testRepositoryFindAllWithDeletedWhenDisabled(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->entityManager = new EntityManager($this->connection);
        $this->entityManager->getFilters()->add('soft_delete', new SoftDeleteFilter());
        $this->entityManager->getFilters()->disable('soft_delete');
        $this->repository = new ProductRepository($this->entityManager, SoftDeleteableProduct::class);

        $this->connection->executeQuery(
            'INSERT INTO soft_deleteable_product (name, deleted_at) VALUES (?, ?), (?, ?), (?, ?)',
            ['Product 1', null, 'Product 2', null, 'Deleted Product', (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $allProducts = $this->repository->findAll();

        $this->assertCount(3, $allProducts);
    }
}
