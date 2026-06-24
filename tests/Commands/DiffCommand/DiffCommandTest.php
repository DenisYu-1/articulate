<?php

namespace Articulate\Tests\Commands\DiffCommand;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Commands\DiffCommand;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Tests\DatabaseTestCase;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Tester\CommandTester;

class DiffCommandTest extends DatabaseTestCase {
    private DatabaseSchemaComparator $schemaComparator;

    private MigrationsCommandGenerator $commandGenerator;

    private string $entitiesPath;

    private string $migrationsPath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/articulate_test_' . uniqid();
        $this->entitiesPath = __DIR__ . '/TestEntities';
        $this->migrationsPath = $this->tempDir . '/migrations';

        mkdir($this->migrationsPath, 0777, true);

        $this->schemaComparator = $this->createStub(DatabaseSchemaComparator::class);

        // Create a real MigrationsCommandGenerator with a mock connection
        $mockConnection = $this->createStub(Connection::class);
        $mockConnection->method('getDriverName')->willReturn(Connection::MYSQL);
        $this->commandGenerator = new MigrationsCommandGenerator($mockConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp directory
        $this->removeDirectory($this->tempDir);
    }

    private function findMigrationFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->migrationsPath));
        $migrationFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $migrationFiles[] = $file->getPathname();
            }
        }

        return $migrationFiles;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function writeFixtureClass(string $directory, string $fileName, string $namespace, string $className, bool $entity): string
    {
        $attribute = $entity ? "#[\\Articulate\\Attributes\\Entity]\n" : '';
        $path = $directory . '/' . $fileName;
        file_put_contents($path, <<<PHP
<?php

namespace {$namespace};

{$attribute}class {$className} {
}
PHP);
        require_once $path;

        return $namespace . '\\' . $className;
    }

    /**
     * Test diff command finds entities and generates migration when schema differences exist.
     */
    public function testGeneratesMigrationWhenSchemaDifferencesExist(): void
    {
        // Manually include the test entity file
        require_once __DIR__ . '/TestEntities/TestEntity.php';

        // Mock schema comparator to return differences for our test entity
        $compareResult = new TableCompareResult('test_entities', TableCompareResult::OPERATION_CREATE);
        $this->schemaComparator->method('compareAll')->willReturn([$compareResult]);

        // Real command generator will process the TableCompareResult and generate actual SQL

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath,
            'Test\Migrations'
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        // Check that migration file was created
        $migrationFiles = $this->findMigrationFiles();
        $this->assertCount(1, $migrationFiles, 'Expected exactly one migration file to be created');

        $migrationContent = file_get_contents($migrationFiles[0]);
        // Assert that the real generator produced SQL for the test entity
        $this->assertStringContainsString('CREATE TABLE', $migrationContent);
        $this->assertStringContainsString('DROP TABLE', $migrationContent);
        $this->assertStringContainsString('Test\Migrations', $migrationContent);
    }

    public function testDiscoversOnlyEntityClassesFromPhpFiles(): void
    {
        $entitiesPath = $this->tempDir . '/scan_entities';
        $nestedPath = $entitiesPath . '/Nested';
        mkdir($nestedPath, 0777, true);

        $suffix = str_replace('.', '', uniqid('', true));
        $entityClass = $this->writeFixtureClass($entitiesPath, 'ScannedEntity.php', 'Articulate\\Tests\\Generated\\Diff' . $suffix, 'ScannedEntity', true);
        $nestedEntityClass = $this->writeFixtureClass($nestedPath, 'NestedEntity.php', 'Articulate\\Tests\\Generated\\Diff' . $suffix . '\\Nested', 'NestedEntity', true);
        $this->writeFixtureClass($entitiesPath, 'PlainClass.php', 'Articulate\\Tests\\Generated\\Diff' . $suffix, 'PlainClass', false);
        file_put_contents($entitiesPath . '/Ignored.txt', "<?php\n#[\\Articulate\\Attributes\\Entity]\nclass IgnoredTextFile {}\n");

        $this->schemaComparator = $this->createMock(DatabaseSchemaComparator::class);
        $this->schemaComparator->expects($this->once())
            ->method('compareAll')
            ->with($this->callback(function (array $entities) use ($entityClass, $nestedEntityClass): bool {
                $this->assertContainsOnlyInstancesOf(ReflectionEntity::class, $entities);
                $classNames = array_map(fn (ReflectionEntity $entity) => $entity->getName(), array_values($entities));
                sort($classNames);
                $expected = [$entityClass, $nestedEntityClass];
                sort($expected);
                $this->assertSame($expected, $classNames);

                return true;
            }))
            ->willReturn([]);

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $entitiesPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('Schema is already in sync', $commandTester->getDisplay());
    }

    public function testWritesWarningsAndEscapedRollbackInGeneratedMigration(): void
    {
        require_once __DIR__ . '/TestEntities/TestEntity.php';

        $compareResult = new TableCompareResult('quoted_table', TableCompareResult::OPERATION_CREATE);
        $compareResult->warnings[] = 'manual review required';

        $this->schemaComparator->method('compareAll')->willReturn([$compareResult]);

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath,
            'Test\Migrations'
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('manual review required', $commandTester->getDisplay());

        $migrationFiles = $this->findMigrationFiles();
        $this->assertCount(1, $migrationFiles);
        $migrationContent = file_get_contents($migrationFiles[0]);
        $this->assertStringContainsString('$this->addSql(\'CREATE TABLE `quoted_table` ()\');', $migrationContent);
        $this->assertStringContainsString('$this->addSql(\'DROP TABLE `quoted_table`\');', $migrationContent);
        $this->assertLessThan(
            strpos($migrationContent, '$this->addSql(\'DROP TABLE `quoted_table`\');'),
            strpos($migrationContent, '$this->addSql(\'CREATE TABLE `quoted_table` ()\');')
        );
    }

    public function testPostgresqlGeneratedMigrationUsesSingleQuotedPhpStringLiterals(): void
    {
        require_once __DIR__ . '/TestEntities/TestEntity.php';

        $compareResult = new TableCompareResult(
            name: 'quoted_table',
            operation: CompareResult::OPERATION_CREATE,
            columns: [
                new ColumnCompareResult(
                    name: 'status',
                    operation: CompareResult::OPERATION_CREATE,
                    propertyData: new PropertiesData(type: 'string', isNullable: false, defaultValue: 'active'),
                    columnData: new PropertiesData(),
                ),
            ],
        );

        $this->schemaComparator->method('compareAll')->willReturn([$compareResult]);

        $mockConnection = $this->createStub(Connection::class);
        $mockConnection->method('getDriverName')->willReturn(Connection::PGSQL);
        $commandGenerator = new MigrationsCommandGenerator($mockConnection);

        $command = new DiffCommand(
            $this->schemaComparator,
            $commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath,
            'Test\Migrations'
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        $migrationFiles = $this->findMigrationFiles();
        $this->assertCount(1, $migrationFiles);
        $migrationContent = file_get_contents($migrationFiles[0]);

        $this->assertStringContainsString(
            '$this->addSql(\'CREATE TABLE "quoted_table" ("status" VARCHAR(255) NOT NULL DEFAULT \\\'active\\\')\');',
            $migrationContent
        );
        $this->assertStringContainsString(
            '$this->addSql(\'DROP TABLE "quoted_table"\');',
            $migrationContent
        );
        $this->assertStringNotContainsString('\\"quoted_table\\"', $migrationContent);
    }

    /**
     * Test diff command shows success message when schema is in sync.
     */
    public function testShowsSuccessWhenNoDifferences(): void
    {
        // Mock schema comparator to return no differences
        $this->schemaComparator->method('compareAll')->willReturn([]);

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Schema is already in sync', $output);

        // No migration files should be created
        $migrationFiles = glob($this->migrationsPath . '/*.php');
        $this->assertEmpty($migrationFiles);
    }

    /**
     * Test diff command with custom entities path.
     */
    public function testUsesCustomEntitiesPath(): void
    {
        $customPath = $this->tempDir . '/custom_entities';
        mkdir($customPath, 0777, true);

        // Mock schema comparator to return no differences (so we don't need actual entity files)
        $this->schemaComparator->method('compareAll')->willReturn([]);

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $customPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Schema is already in sync', $output);
    }

    /**
     * Test diff command with default entities path resolution.
     */
    public function testResolvesDefaultEntitiesPath(): void
    {
        // Create a default entities directory structure
        $defaultEntitiesPath = $this->tempDir . '/src/Entities';
        mkdir($defaultEntitiesPath, 0777, true);

        // Change to the temp directory so the default path resolution works
        $oldCwd = getcwd();
        chdir($this->tempDir);

        try {
            // Mock schema comparator to return no differences
            $this->schemaComparator->method('compareAll')->willReturn([]);

            $command = new DiffCommand(
                $this->schemaComparator,
                $this->commandGenerator,
                $this->migrationsPath,
                null // No custom path - should use default
            );

            $commandTester = new CommandTester($command);
            $statusCode = $commandTester->execute([]);

            $this->assertSame(0, $statusCode);

            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('Schema is already in sync', $output);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testHandlesMultipleEntities(): void
    {
        // Mock schema comparator to return multiple differences
        $diff1 = new TableCompareResult('users', TableCompareResult::OPERATION_CREATE);
        $diff2 = new TableCompareResult('products', TableCompareResult::OPERATION_CREATE);
        $this->schemaComparator->method('compareAll')->willReturn([$diff1, $diff2]);

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        // Check that migration file was created with both changes
        $iterator = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->migrationsPath));
        $migrationFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $migrationFiles[] = $file->getPathname();
            }
        }
        $this->assertCount(1, $migrationFiles);

        $migrationContent = file_get_contents($migrationFiles[0]);
        // Assert that the real generator produced SQL containing table creation
        $this->assertStringContainsString('CREATE TABLE', $migrationContent);
        $this->assertStringContainsString('`users`', $migrationContent);
        $this->assertStringContainsString('`products`', $migrationContent);
        $this->assertStringContainsString('DROP TABLE', $migrationContent);
    }

    /**
     * Test diff command throws exception when entities directory not found.
     */
    public function testThrowsExceptionWhenEntitiesDirectoryNotFound(): void
    {
        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            '/nonexistent/path'
        );

        $commandTester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entities directory not found at configured path: /nonexistent/path');

        $commandTester->execute([]);
    }

    /**
     * Test diff command filters out empty migration commands.
     */
    public function testFiltersEmptyMigrationCommands(): void
    {
        // Mock schema comparator to return no differences (schema is in sync)
        $this->schemaComparator->method('compareAll')->willReturn([]);

        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Schema is already in sync', $output);

        // No migration files should be created when all commands are empty
        $migrationFiles = glob($this->migrationsPath . '/*.php');
        $this->assertEmpty($migrationFiles);
    }

    public function testCommandConfiguration(): void
    {
        $command = new DiffCommand(
            $this->schemaComparator,
            $this->commandGenerator,
            $this->migrationsPath,
            $this->entitiesPath
        );

        $this->assertEquals('articulate:diff', $command->getName());
        $this->assertIsString($command->getDescription());
    }

    /**
     * Test diff command throws exception when no default entities directories exist.
     */
    public function testThrowsExceptionWhenNoDefaultEntitiesDirectoryExists(): void
    {
        $oldCwd = getcwd();
        chdir($this->tempDir);

        try {
            $command = new DiffCommand(
                $this->schemaComparator,
                $this->commandGenerator,
                $this->migrationsPath,
                null
            );

            $commandTester = new CommandTester($command);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Entities directory is not found. Expected one of: src/Entities, src/Entity, or set a custom path.');

            $commandTester->execute([]);
        } finally {
            chdir($oldCwd);
        }
    }
}
