<?php

namespace Articulate\Tests\Commands\ValidateCommand;

use Articulate\Commands\ValidateCommand;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ValidateCommandVersionTest extends TestCase {
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/articulate_validate_version_' . uniqid();
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

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function writeFixture(string $fileName, string $namespace, string $body): void
    {
        $path = $this->tempDir . '/' . $fileName;
        file_put_contents($path, "<?php\n\nnamespace {$namespace};\n\n{$body}");
        require_once $path;
    }

    private function runValidate(): CommandTester
    {
        $schemaComparator = $this->createStub(DatabaseSchemaComparator::class);
        $schemaComparator->method('compareAll')->willReturn([]);

        $command = new ValidateCommand($schemaComparator, [$this->tempDir]);
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }

    public function testErrorsWhenSiblingDoesNotAccountForVersionColumn(): void
    {
        $ns = 'Articulate\\Tests\\Generated\\ValidateVersion' . str_replace('.', '', uniqid('', true));

        $this->writeFixture('Owner.php', $ns, <<<'PHP'
#[\Articulate\Attributes\Entity(tableName: 'validate_version_missing')]
class Owner {
    #[\Articulate\Attributes\Indexes\PrimaryKey]
    public ?int $id = null;

    #[\Articulate\Attributes\Property]
    #[\Articulate\Attributes\Version]
    public int $version = 0;
}
PHP);

        $this->writeFixture('Uncovered.php', $ns, <<<'PHP'
#[\Articulate\Attributes\Entity(tableName: 'validate_version_missing')]
class Uncovered {
    #[\Articulate\Attributes\Indexes\PrimaryKey]
    public ?int $id = null;

    #[\Articulate\Attributes\Property]
    public string $name = '';
}
PHP);

        $tester = $this->runValidate();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('does not account for version column', $tester->getDisplay());
    }

    public function testErrorsForDanglingVersionAwareColumn(): void
    {
        $ns = 'Articulate\\Tests\\Generated\\ValidateVersion' . str_replace('.', '', uniqid('', true));

        $this->writeFixture('Dangling.php', $ns, <<<'PHP'
#[\Articulate\Attributes\Entity(tableName: 'validate_version_dangling')]
#[\Articulate\Attributes\VersionAware(['ghost_version'])]
class Dangling {
    #[\Articulate\Attributes\Indexes\PrimaryKey]
    public ?int $id = null;
}
PHP);

        $tester = $this->runValidate();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('dangling', strtolower($tester->getDisplay()));
    }

    public function testInfoForMultipleDistinctVersionColumns(): void
    {
        $ns = 'Articulate\\Tests\\Generated\\ValidateVersion' . str_replace('.', '', uniqid('', true));

        $this->writeFixture('First.php', $ns, <<<'PHP'
#[\Articulate\Attributes\Entity(tableName: 'validate_version_multi')]
class First {
    #[\Articulate\Attributes\Indexes\PrimaryKey]
    public ?int $id = null;

    #[\Articulate\Attributes\Property]
    #[\Articulate\Attributes\Version]
    public int $version = 0;
}
PHP);

        $this->writeFixture('Second.php', $ns, <<<'PHP'
#[\Articulate\Attributes\Entity(tableName: 'validate_version_multi')]
class Second {
    #[\Articulate\Attributes\Indexes\PrimaryKey]
    public ?int $id = null;

    #[\Articulate\Attributes\Property(name: 'revision')]
    #[\Articulate\Attributes\Version]
    public int $revision = 0;
}
PHP);

        $tester = $this->runValidate();

        $this->assertStringContainsString('multiple distinct #[Version] columns', $tester->getDisplay());
    }

    public function testNoVersionErrorsWhenClassFullyCoversItsOwnColumn(): void
    {
        $ns = 'Articulate\\Tests\\Generated\\ValidateVersion' . str_replace('.', '', uniqid('', true));

        $this->writeFixture('Solo.php', $ns, <<<'PHP'
#[\Articulate\Attributes\Entity(tableName: 'validate_version_solo')]
class Solo {
    #[\Articulate\Attributes\Indexes\PrimaryKey]
    public ?int $id = null;

    #[\Articulate\Attributes\Property]
    #[\Articulate\Attributes\Version]
    public int $version = 0;
}
PHP);

        $tester = $this->runValidate();

        $this->assertSame(0, $tester->getStatusCode());
    }
}
