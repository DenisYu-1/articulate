<?php

namespace Articulate\Tests\Commands\ValidateCommand;

use Articulate\Commands\ValidateCommand;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ValidateCommandTest extends TestCase {
    private string $entitiesPath;

    protected function setUp(): void
    {
        $this->entitiesPath = __DIR__ . '/../DiffCommand/TestEntities';
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

    public function testValidationThrowsForNonExistentEntitiesPath(): void
    {
        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);

        $command = new ValidateCommand($schemaComparator, '/nonexistent/path/that/does/not/exist');
        $commandTester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $commandTester->execute([]);
    }
}
