<?php

namespace Articulate\Tests\Commands\ValidateCommand;

use Articulate\Commands\ValidateCommand;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ValidateCommandTest extends TestCase {
    private string $entitiesPath;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->entitiesPath = __DIR__ . '/../DiffCommand/TestEntities';
        $this->tempDir = sys_get_temp_dir() . '/articulate_validate_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
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

    public function testValidationPassesWhenSchemaIsInSync(): void
    {
        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);
        $schemaComparator->method('compareAll')->willReturn([]);

        $command = new ValidateCommand($schemaComparator, $this->entitiesPath);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('valid', $commandTester->getDisplay());
    }

    public function testValidationFailsWhenSchemaHasDrift(): void
    {
        $compareResult = new TableCompareResult('test_entities', TableCompareResult::OPERATION_CREATE);

        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);
        $schemaComparator->method('compareAll')->willReturn([$compareResult]);

        $command = new ValidateCommand($schemaComparator, $this->entitiesPath);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(1, $statusCode);
    }

    public function testDiscoversOnlyEntityClassesFromPhpFiles(): void
    {
        $entitiesPath = $this->tempDir . '/scan_entities';
        $nestedPath = $entitiesPath . '/Nested';
        mkdir($nestedPath, 0777, true);

        $suffix = str_replace('.', '', uniqid('', true));
        $entityClass = $this->writeFixtureClass($entitiesPath, 'ScannedEntity.php', 'Articulate\\Tests\\Generated\\Validate' . $suffix, 'ScannedEntity', true);
        $nestedEntityClass = $this->writeFixtureClass($nestedPath, 'NestedEntity.php', 'Articulate\\Tests\\Generated\\Validate' . $suffix . '\\Nested', 'NestedEntity', true);
        $this->writeFixtureClass($entitiesPath, 'PlainClass.php', 'Articulate\\Tests\\Generated\\Validate' . $suffix, 'PlainClass', false);
        file_put_contents($entitiesPath . '/Ignored.txt', "<?php\n#[\\Articulate\\Attributes\\Entity]\nclass IgnoredTextFile {}\n");

        $schemaComparator = $this->createMock(DatabaseSchemaComparator::class);
        $schemaComparator->expects($this->once())
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

        $command = new ValidateCommand($schemaComparator, $entitiesPath);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('valid', $commandTester->getDisplay());
    }

    public function testValidationReportsWarningsAsFailureWithoutDrift(): void
    {
        $compareResult = new TableCompareResult('test_entities', TableCompareResult::OPERATION_UPDATE);
        $compareResult->warnings[] = 'unmapped required column';

        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);
        $schemaComparator->method('compareAll')->willReturn([$compareResult]);

        $command = new ValidateCommand($schemaComparator, $this->entitiesPath);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(1, $statusCode);
        $this->assertStringContainsString('unmapped required column', $commandTester->getDisplay());
        $this->assertStringContainsString('out of sync', $commandTester->getDisplay());
    }

    public function testValidationThrowsForNonExistentEntitiesPath(): void
    {
        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);

        $command = new ValidateCommand($schemaComparator, '/nonexistent/path/that/does/not/exist');
        $commandTester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $commandTester->execute([]);
    }
}
