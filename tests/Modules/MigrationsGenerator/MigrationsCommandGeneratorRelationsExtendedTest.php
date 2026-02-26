<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorRelationsExtendedTest extends DatabaseTestCase {
    /**
     * Test ManyToOne relation creates foreign key.
     */
    #[DataProvider('databaseProvider')]
    public function testManyToOneRelation(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'posts',
            CompareResult::OPERATION_UPDATE,
            [
                new ColumnCompareResult(
                    'user_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('posts', 'users', 'user_id'),
                    CompareResult::OPERATION_CREATE,
                    'user_id',
                    'users'
                ),
            ]
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $result = $generator->generate($tableCompareResult);

        $expected = "ALTER TABLE {$quote}posts{$quote} ADD {$quote}user_id{$quote} {$intType} NOT NULL, ADD CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('posts', 'users', 'user_id') . "{$quote} FOREIGN KEY ({$quote}user_id{$quote}) REFERENCES {$quote}users{$quote}({$quote}id{$quote})";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test OneToMany relation inverse (no SQL generated, just logical).
     */
    #[DataProvider('databaseProvider')]
    public function testOneToManyRelationInverse(string $databaseName): void
    {
        // OneToMany relations don't generate SQL - they're just the inverse side
        // The foreign key is created by the owning ManyToOne side
        $tableCompareResult = new TableCompareResult(
            'users',
            CompareResult::OPERATION_UPDATE,
            [], // No new columns
            [], // No new indexes
            []  // No new foreign keys
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $result = $generator->generate($tableCompareResult);

        // Should generate no SQL changes
        $this->assertEquals('', $result);
    }

    /**
     * Test OneToOne relation with foreign key on owning side.
     */
    #[DataProvider('databaseProvider')]
    public function testOneToOneRelationOwningSide(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'users',
            CompareResult::OPERATION_UPDATE,
            [
                new ColumnCompareResult(
                    'profile_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', true), // nullable for OneToOne
                    new PropertiesData()
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('users', 'profiles', 'profile_id'),
                    CompareResult::OPERATION_CREATE,
                    'profile_id',
                    'profiles'
                ),
            ]
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $result = $generator->generate($tableCompareResult);

        $expected = "ALTER TABLE {$quote}users{$quote} ADD {$quote}profile_id{$quote} {$intType}, ADD CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('users', 'profiles', 'profile_id') . "{$quote} FOREIGN KEY ({$quote}profile_id{$quote}) REFERENCES {$quote}profiles{$quote}({$quote}id{$quote})";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test dropping foreign key from relation.
     */
    #[DataProvider('databaseProvider')]
    public function testDropRelationForeignKey(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'posts',
            CompareResult::OPERATION_UPDATE,
            [],
            [],
            [
                new ForeignKeyCompareResult(
                    'fk_posts_users_user_id',
                    CompareResult::OPERATION_DELETE,
                    'user_id',
                    'users'
                ),
            ]
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $fkKeyword = match ($databaseName) {
            'mysql' => 'FOREIGN KEY',
            'pgsql' => 'CONSTRAINT',
        };

        $result = $generator->generate($tableCompareResult);

        $expected = "ALTER TABLE {$quote}posts{$quote} DROP {$fkKeyword} {$quote}fk_posts_users_user_id{$quote}";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test cascading foreign key constraints.
     */
    #[DataProvider('databaseProvider')]
    public function testCascadingForeignKey(string $databaseName): void
    {
        // Note: The current implementation doesn't handle cascade options yet
        // This test documents the expected behavior when cascade options are added
        $this->markTestSkipped('Cascade options not yet implemented in current version');

        // Future test would check:
        // ON DELETE CASCADE
        // ON UPDATE CASCADE
        // ON DELETE SET NULL
        // etc.
    }
}
