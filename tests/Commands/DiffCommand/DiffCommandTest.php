<?php

namespace Articulate\Tests\Commands\DiffCommand;

use Articulate\Commands\DiffCommand;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Tester\CommandTester;

class DiffCommandTest extends DatabaseTestCase {
    private MockObject&DatabaseSchemaComparator $schemaComparator;

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

        $this->schemaComparator = $this->createMock(DatabaseSchemaComparator::class);

        // Create a real MigrationsCommandGenerator with a mock connection
        $mockConnection = $this->createMock(Connection::class);
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
            $this->entitiesPath,
            $this->migrationsPath,
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
            $this->entitiesPath,
            $this->migrationsPath
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
            $customPath,
            $this->migrationsPath
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
                null, // No custom path - should use default
                $this->migrationsPath
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
            $this->entitiesPath,
            $this->migrationsPath
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
            '/nonexistent/path',
            $this->migrationsPath
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
            $this->entitiesPath,
            $this->migrationsPath
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
            $this->entitiesPath,
            $this->migrationsPath
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
                null,
                $this->migrationsPath
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
