<?php

namespace Articulate\Tests\Integration;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\Index;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\SchemaReaderFactory;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Entity(tableName: 'idempotence_categories')]
#[Index(fields: ['slug'], unique: true, name: 'idx_idem_categories_slug')]
class IdempotenceCategory {
    #[PrimaryKey(type: 'int', generator: PrimaryKey::GENERATOR_AUTO_INCREMENT)]
    public ?int $id = null;

    #[Property(type: 'string', maxLength: 120)]
    public string $name;

    #[Property(type: 'string', maxLength: 120)]
    public string $slug;

    #[OneToMany(targetEntity: IdempotenceProduct::class, ownedBy: 'category')]
    public array $products;
}

#[Entity(tableName: 'idempotence_products')]
#[Index(fields: ['sku'], unique: true, name: 'idx_idem_products_sku')]
#[Index(fields: ['productName'], name: 'idx_idem_products_name')]
class IdempotenceProduct {
    #[PrimaryKey(type: 'int', generator: PrimaryKey::GENERATOR_AUTO_INCREMENT)]
    public ?int $id = null;

    #[Property(type: 'string', maxLength: 64)]
    public string $sku;

    #[Property(name: 'product_name', type: 'string', maxLength: 180)]
    public string $productName;

    #[Property(type: 'float')]
    public float $price;

    #[Property(type: 'DateTime')]
    public DateTime $createdAt;

    #[Property(type: 'string', nullable: true, maxLength: 255)]
    public ?string $description = null;

    #[ManyToOne(targetEntity: IdempotenceCategory::class, referencedBy: 'products', nullable: false)]
    public IdempotenceCategory $category;

    #[ManyToMany(
        targetEntity: IdempotenceTag::class,
        referencedBy: 'products',
        mappingTable: new MappingTable(
            name: 'idempotence_product_tags',
            properties: [
                new MappingTableProperty('assigned_at', 'datetime', nullable: true),
                new MappingTableProperty('assignment_source', 'string', nullable: true),
                new MappingTableProperty('assignment_note', 'string', nullable: true, length: 120),
            ],
        ),
    )]
    public array $tags;
}

#[Entity(tableName: 'idempotence_tags')]
#[Index(fields: ['name'], unique: true, name: 'idx_idem_tags_name')]
class IdempotenceTag {
    #[PrimaryKey(type: 'int', generator: PrimaryKey::GENERATOR_AUTO_INCREMENT)]
    public ?int $id = null;

    #[Property(type: 'string', maxLength: 80)]
    public string $name;

    #[ManyToMany(targetEntity: IdempotenceProduct::class, ownedBy: 'tags')]
    public array $products;
}

class MigrationDiffIdempotenceTest extends DatabaseTestCase {
    private const TABLES = [
        'idempotence_product_tags',
        'idempotence_products',
        'idempotence_tags',
        'idempotence_categories',
    ];

    #[DataProvider('databaseProvider')]
    #[Group('database')]
    public function testGeneratedMigrationIsIdempotent(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->cleanUpTables(self::TABLES);

        $firstDiff = $this->diff($connection);
        $this->assertNotEmpty($firstDiff);

        $generator = match ($databaseName) {
            Connection::MYSQL => MigrationsGeneratorTestHelper::forMySql(),
            Connection::PGSQL => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $generatedSql = [];
        foreach ($firstDiff as $tableDiff) {
            foreach ($generator->generate($tableDiff) as $statement) {
                $generatedSql[] = $statement;
                $connection->executeQuery($statement);
            }
        }

        $this->assertNotEmpty($generatedSql);
        $this->assertSchemaReaderSeesAppliedSchema($connection);

        $secondDiff = $this->diff($connection);
        $secondSql = [];
        foreach ($secondDiff as $tableDiff) {
            $secondSql = array_merge($secondSql, $generator->generate($tableDiff));
        }

        $this->assertSame([], $secondDiff);
        $this->assertSame([], $secondSql);
    }

    /**
     * @return array<int, TableCompareResult>
     */
    private function diff(Connection $connection): array
    {
        $comparator = new DatabaseSchemaComparator(
            SchemaReaderFactory::create($connection),
            new SchemaNaming(),
        );

        return iterator_to_array($comparator->compareAll([
            new ReflectionEntity(IdempotenceCategory::class),
            new ReflectionEntity(IdempotenceProduct::class),
            new ReflectionEntity(IdempotenceTag::class),
        ]));
    }

    private function assertSchemaReaderSeesAppliedSchema(Connection $connection): void
    {
        $reader = SchemaReaderFactory::create($connection);

        $productColumns = $reader->getTableColumns('idempotence_products');
        $this->assertNotEmpty($productColumns);
        $this->assertContains('product_name', array_map(static fn ($column) => $column->name, $productColumns));

        $productIndexes = $reader->getTableIndexes('idempotence_products');
        $this->assertArrayHasKey('idx_idem_products_sku', $productIndexes);
        $this->assertArrayHasKey('idx_idem_products_name', $productIndexes);

        $productForeignKeys = $reader->getTableForeignKeys('idempotence_products');
        $this->assertNotEmpty($productForeignKeys);
        $this->assertContains('category_id', array_column($productForeignKeys, 'column'));

        $mappingForeignKeys = $reader->getTableForeignKeys('idempotence_product_tags');
        $this->assertCount(2, $mappingForeignKeys);

        $mappingColumns = $reader->getTableColumns('idempotence_product_tags');
        $mappingColumnNames = array_map(static fn ($column) => $column->name, $mappingColumns);
        $this->assertContains('assigned_at', $mappingColumnNames);
        $this->assertContains('assignment_source', $mappingColumnNames);
        $this->assertContains('assignment_note', $mappingColumnNames);
    }
}
