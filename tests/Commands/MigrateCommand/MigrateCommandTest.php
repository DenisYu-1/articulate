<?php

namespace Articulate\Tests\Commands\MigrateCommand;

use Articulate\Commands\InitCommand;
use Articulate\Commands\MigrateCommand;
use Articulate\Connection;
use Articulate\Modules\Migrations\ExecutionStrategies\MigrationExecutionStrategy;
use Articulate\Modules\Migrations\ExecutionStrategies\RollbackExecutionStrategy;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateCommandTest extends DatabaseTestCase {
    private string $migrationsPath;

    private string $tempDir;

    private MockObject&InitCommand $initCommand;

    private MockObject&MigrationExecutionStrategy $migrationStrategy;

    private MockObject&RollbackExecutionStrategy $rollbackStrategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/articulate_migrate_test_' . uniqid();
        $this->migrationsPath = $this->tempDir . '/migrations';
        mkdir($this->migrationsPath, 0777, true);

        $this->initCommand = $this->createMock(InitCommand::class);
        $this->migrationStrategy = $this->createMock(MigrationExecutionStrategy::class);
        $this->rollbackStrategy = $this->createMock(RollbackExecutionStrategy::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp directory
        $this->removeDirectory($this->tempDir);
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
     * Test migrate command executes successfully with migrations.
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testExecutesMigrationsSuccessfully(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Use a real InitCommand to ensure migrations table exists
        $realInitCommand = new InitCommand($connection);

        // Create a unique migration class name to avoid conflicts
        $uniqueId = uniqid();
        $className = "TestMigration{$uniqueId}";
        $fileName = "{$className}.php";

        // Create a sample migration file
        $migrationContent = <<<PHP
<?php

namespace Test\Migrations;

use Articulate\Modules\Migrations\Generator\BaseMigration;

class {$className} extends BaseMigration {
    protected function up(): void
    {
        \$this->addSql('CREATE TABLE test (id INT PRIMARY KEY)');
    }

    protected function down(): void
    {
        \$this->addSql('DROP TABLE test');
    }
}
PHP;

        file_put_contents($this->migrationsPath . '/' . $fileName, $migrationContent);

        $command = new MigrateCommand(
            $connection,
            $realInitCommand,
            $this->migrationsPath
        );

        // Test the basic execution flow
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
    }

    /**
     * Test migrate command with rollback argument.
     */
    public function testExecutesRollbackWhenRollbackArgumentProvided(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->initCommand
            ->expects($this->once())
            ->method('ensureMigrationsTableExists');

        // Mock connection to return empty migrations
        $resultMock = $this->createMock(\PDOStatement::class);
        $resultMock->method('fetchAll')->willReturn([]);

        $latestResultMock = $this->createMock(\PDOStatement::class);
        $latestResultMock->method('fetch')->willReturn(false); // No latest migration

        $connection
            ->expects($this->exactly(2))
            ->method('executeQuery')
            ->willReturnCallback(function ($query) use ($resultMock, $latestResultMock) {
                if ($query === 'SELECT * FROM migrations') {
                    return $resultMock;
                } elseif ($query === 'SELECT name FROM migrations ORDER BY id DESC LIMIT 1') {
                    return $latestResultMock;
                }

                return null;
            });

        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            $this->migrationsPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute(['rollback' => 'rollback']);

        $this->assertSame(0, $statusCode);
    }

    /**
     * Test migrate command shows warning when migrations directory does not exist.
     */
    public function testShowsWarningWhenMigrationsDirectoryDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->initCommand
            ->expects($this->once())
            ->method('ensureMigrationsTableExists');

        $nonExistentPath = $this->tempDir . '/nonexistent';

        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            $nonExistentPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migrations directory does not exist', $output);
        $this->assertStringContainsString($nonExistentPath, $output);
    }

    /**
     * Test migrate command executes with specified migrations path.
     */
    public function testExecutesWithSpecifiedMigrationsPath(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->initCommand
            ->expects($this->once())
            ->method('ensureMigrationsTableExists');

        $connection->expects($this->atLeastOnce())
            ->method('executeQuery');

        // Use temp directory for migrations
        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            $this->migrationsPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        // Should check for migrations since directory exists but is empty
        $this->assertStringContainsString('No new migrations to execute', $output);
    }

    /**
     * Test migrate command configuration.
     */
    public function testCommandConfiguration(): void
    {
        $connection = $this->createMock(Connection::class);

        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            '/tmp/test'
        );

        $this->assertSame('articulate:migrate', $command->getName());
        $this->assertSame('Run database migrations', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('rollback'));
        $this->assertFalse($definition->getArgument('rollback')->isRequired());
    }

    /**
     * Test migrate command ensures migrations table exists before proceeding.
     */
    public function testEnsuresMigrationsTableExists(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->initCommand
            ->expects($this->once())
            ->method('ensureMigrationsTableExists');

        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            $this->migrationsPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
    }

    /**
     * Test migrate command queries executed migrations from database.
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testQueriesExecutedMigrationsFromDatabase(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Create migrations table first
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE migrations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), executed_at DATETIME, running_time INT)',
            'pgsql' => 'CREATE TABLE migrations (id SERIAL PRIMARY KEY, name VARCHAR(255), executed_at TIMESTAMP, running_time INT)'
        };

        $connection->executeQuery($createTableSql);

        // Insert some sample migrations
        $insertSql = match ($databaseName) {
            'mysql' => "INSERT INTO migrations (name, executed_at, running_time) VALUES ('Migration1', NOW(), 100), ('Migration2', NOW(), 200)",
            'pgsql' => "INSERT INTO migrations (name, executed_at, running_time) VALUES ('Migration1', NOW(), 100), ('Migration2', NOW(), 200)"
        };

        $connection->executeQuery($insertSql);

        $this->initCommand
            ->expects($this->once())
            ->method('ensureMigrationsTableExists');

        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            $this->migrationsPath
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
    }

    /**
     * Test migrate command handles empty migrations directory.
     */
    public function testHandlesEmptyMigrationsDirectory(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->initCommand
            ->expects($this->once())
            ->method('ensureMigrationsTableExists');

        // Mock connection to return empty result set
        $resultMock = $this->createMock(\PDOStatement::class);
        $resultMock->method('fetchAll')->willReturn([]);

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM migrations')
            ->willReturn($resultMock);

        $command = new MigrateCommand(
            $connection,
            $this->initCommand,
            $this->migrationsPath // Empty directory
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
    }
}
