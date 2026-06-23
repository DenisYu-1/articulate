<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\Index as EntityIndex;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorIndexesTest extends DatabaseTestCase {
    /**
     * Test dropping indexes for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testDropsIndex(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_id',
                    operation: CompareResult::OPERATION_DELETE,
                    columns: ['id'],
                    isUnique: false,
                    isConcurrent: false,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $expected = match ($databaseName) {
            'mysql' => ["ALTER TABLE {$quote}test_table{$quote} DROP INDEX {$quote}idx_test_table_id{$quote}"],
            'pgsql' => ['DROP INDEX "idx_test_table_id"'],
        };

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test dropping indexes with column updates for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testDropsIndexOnUpdate(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [
                new ColumnCompareResult(
                    name: 'id',
                    operation: CompareResult::OPERATION_UPDATE,
                    propertyData: new PropertiesData('string', false),
                    columnData: new PropertiesData('string', false),
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_id',
                    operation: CompareResult::OPERATION_DELETE,
                    columns: ['id'],
                    isUnique: false,
                    isConcurrent: false,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $updateSyntax = match ($databaseName) {
            'mysql' => 'MODIFY',
            'pgsql' => 'ALTER COLUMN',
        };

        $expected = match ($databaseName) {
            'mysql' => ["ALTER TABLE {$quote}test_table{$quote} DROP INDEX {$quote}idx_test_table_id{$quote}, {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL"],
            'pgsql' => [
                'DROP INDEX "idx_test_table_id"',
                'ALTER TABLE "test_table" ALTER COLUMN "id" VARCHAR(255) NOT NULL',
            ],
        };

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test restoring deleted indexes on rollback for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testRestoresDeletedIndexOnRollback(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [
                new ColumnCompareResult(
                    name: 'id',
                    operation: CompareResult::OPERATION_DELETE,
                    propertyData: new PropertiesData('string', false),
                    columnData: new PropertiesData('string', false),
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_id',
                    operation: CompareResult::OPERATION_DELETE,
                    columns: ['id'],
                    isUnique: false,
                    isConcurrent: false,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $expected = match ($databaseName) {
            'mysql' => ["ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(255) NOT NULL, ADD INDEX {$quote}idx_test_table_id{$quote} ({$quote}id{$quote})"],
            'pgsql' => [
                'ALTER TABLE "test_table" ADD "id" VARCHAR(255) NOT NULL',
                'CREATE INDEX "idx_test_table_id" ON "test_table" ("id")',
            ],
        };

        $this->assertEquals(
            $expected,
            $generator->rollback($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test creating concurrent indexes for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testCreatesConcurrentIndex(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_email',
                    operation: CompareResult::OPERATION_CREATE,
                    columns: ['email'],
                    isUnique: true,
                    isConcurrent: true,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $expected = match ($databaseName) {
            'mysql' => ['ALTER TABLE `test_table` ALGORITHM=INPLACE ADD UNIQUE INDEX `idx_test_table_email` (`email`)'],
            'pgsql' => ['CREATE UNIQUE INDEX CONCURRENTLY "idx_test_table_email" ON "test_table" ("email")'],
        };

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test that PostgreSQL CREATE TABLE emits separate CREATE INDEX statements.
     */
    public function testPostgresqlCreateTableWithIndexEmitsSeparateStatements(): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'products',
            operation: CompareResult::OPERATION_CREATE,
            columns: [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'name',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255),
                    new PropertiesData()
                ),
            ],
            indexes: [
                new IndexCompareResult('idx_products_name', CompareResult::OPERATION_CREATE, ['name'], false),
                new IndexCompareResult('idx_products_unique_name', CompareResult::OPERATION_CREATE, ['name'], true),
            ],
            primaryColumns: ['id']
        );

        $result = MigrationsGeneratorTestHelper::forPostgresql()->generate($tableCompareResult);

        $this->assertCount(3, $result);
        $this->assertStringContainsString('CREATE TABLE "products"', $result[0]);
        $this->assertStringNotContainsString('INDEX', $result[0]);
        $this->assertEquals('CREATE INDEX "idx_products_name" ON "products" ("name")', $result[1]);
        $this->assertEquals('CREATE UNIQUE INDEX "idx_products_unique_name" ON "products" ("name")', $result[2]);
    }

    /**
     * Test that MySQL CREATE TABLE keeps indexes inline.
     */
    public function testMysqlCreateTableWithIndexKeepsInline(): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'products',
            operation: CompareResult::OPERATION_CREATE,
            columns: [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'name',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255),
                    new PropertiesData()
                ),
            ],
            indexes: [
                new IndexCompareResult('idx_products_name', CompareResult::OPERATION_CREATE, ['name'], false),
            ],
            primaryColumns: ['id']
        );

        $result = MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('INDEX `idx_products_name`', $result[0]);
    }

    #[DataProvider('databaseProvider')]
    public function testGeneratedSqlUsesRelationColumnsForEntityIndexes(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'migration_index_order_items',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_order_items_order_id',
                    operation: CompareResult::OPERATION_CREATE,
                    columns: $this->resolveEntityIndexColumns(MigrationIndexOrderItemEntity::class, 'idx_order_items_order_id'),
                    isUnique: false,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $result = $generator->generate($tableCompareResult);
        $sql = implode("\n", $result);

        $this->assertStringNotContainsString('INDEX `idx_order_items_order_id` ()', $sql);
        $this->assertStringNotContainsString('INDEX "idx_order_items_order_id" ()', $sql);

        match ($databaseName) {
            'mysql' => $this->assertStringContainsString('INDEX `idx_order_items_order_id` (`order_id`)', $sql),
            'pgsql' => $this->assertStringContainsString('INDEX "idx_order_items_order_id" ON "migration_index_order_items" ("order_id")', $sql),
        };
    }

    #[DataProvider('databaseProvider')]
    public function testGeneratedSqlPreservesMixedRelationAndScalarIndexOrder(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'migration_index_orders',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_orders_customer_status',
                    operation: CompareResult::OPERATION_CREATE,
                    columns: $this->resolveEntityIndexColumns(MigrationIndexOrderEntity::class, 'idx_orders_customer_status'),
                    isUnique: false,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $result = $generator->generate($tableCompareResult);
        $sql = implode("\n", $result);

        $this->assertStringNotContainsString('INDEX `idx_orders_customer_status` ()', $sql);
        $this->assertStringNotContainsString('INDEX "idx_orders_customer_status" ()', $sql);

        match ($databaseName) {
            'mysql' => $this->assertStringContainsString('INDEX `idx_orders_customer_status` (`customer_id`, `status`)', $sql),
            'pgsql' => $this->assertStringContainsString('INDEX "idx_orders_customer_status" ON "migration_index_orders" ("customer_id", "status")', $sql),
        };
    }

    /**
     * @param class-string $entityClass
     * @return string[]
     */
    private function resolveEntityIndexColumns(string $entityClass, string $indexName): array
    {
        $entity = new ReflectionEntity($entityClass);

        foreach ($entity->getAttributes(EntityIndex::class) as $indexAttribute) {
            /** @var EntityIndex $index */
            $index = $indexAttribute->newInstance();
            $index->resolveColumns($entity);

            if ($index->getName() === $indexName) {
                return $index->columns;
            }
        }

        $this->fail(sprintf('Index "%s" was not found on "%s".', $indexName, $entityClass));
    }
}

#[EntityIndex(['order'], name: 'idx_order_items_order_id')]
#[Entity(tableName: 'migration_index_order_items')]
class MigrationIndexOrderItemEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToOne(targetEntity: MigrationIndexOrderEntity::class, referencedBy: 'items', column: 'order_id', nullable: false)]
    public ?MigrationIndexOrderEntity $order = null;
}

#[EntityIndex(['customer', 'status'], name: 'idx_orders_customer_status')]
#[Entity(tableName: 'migration_index_orders')]
class MigrationIndexOrderEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToOne(targetEntity: MigrationIndexCustomerEntity::class, column: 'customer_id', nullable: false)]
    public ?MigrationIndexCustomerEntity $customer = null;

    #[Property(maxLength: 32)]
    public string $status = 'open';
}

#[Entity(tableName: 'migration_index_customers')]
class MigrationIndexCustomerEntity {
    #[PrimaryKey]
    #[Property]
    public int $id;
}
