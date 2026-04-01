<?php

namespace Articulate\Tests\Commands\DiffCommand;

use Articulate\Commands\DiffCommand;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Tests\DatabaseTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DiffCommandNonEntityTest extends DatabaseTestCase {
    private string $tempDir;

    private string $entitiesPath;

    private string $migrationsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/articulate_non_entity_test_' . uniqid();
        $this->entitiesPath = $this->tempDir . '/entities';
        $this->migrationsPath = $this->tempDir . '/migrations';

        mkdir($this->entitiesPath, 0777, true);
        mkdir($this->migrationsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->tempDir);
    }

    public function testNonEntityFilesAreIgnored(): void
    {
        file_put_contents($this->entitiesPath . '/SomeInterface.php', <<<'PHP'
<?php

namespace App\Entities;

interface SomeInterface
{
    public function doSomething(): void;
}
PHP);

        file_put_contents($this->entitiesPath . '/SomeTrait.php', <<<'PHP'
<?php

namespace App\Entities;

trait SomeTrait
{
    public function hello(): string
    {
        return 'hello';
    }
}
PHP);

        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);
        $schemaComparator->method('compareAll')->willReturn([]);

        $mockConnection = $this->createStub(Connection::class);
        $mockConnection->method('getDriverName')->willReturn(Connection::MYSQL);

        $command = new DiffCommand(
            $schemaComparator,
            new MigrationsCommandGenerator($mockConnection),
            $this->migrationsPath,
            $this->entitiesPath,
        );

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('Schema is already in sync', $commandTester->getDisplay());
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
}
