<?php

namespace Articulate\Tests\Commands\WarmMetadataCacheCommand;

use Articulate\Commands\WarmMetadataCacheCommand;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class WarmMetadataCacheCommandTest extends TestCase {
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/articulate_warm_cache_' . uniqid();
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

    public function testWarmsMetadataForEveryDiscoveredEntity(): void
    {
        $entitiesPath = $this->tempDir . '/warm_entities';
        $nestedPath = $entitiesPath . '/Nested';
        mkdir($nestedPath, 0777, true);

        $suffix = str_replace('.', '', uniqid('', true));
        $entityClass = $this->writeFixtureClass($entitiesPath, 'WarmedEntity.php', 'Articulate\\Tests\\Generated\\Warm' . $suffix, 'WarmedEntity', true);
        $nestedEntityClass = $this->writeFixtureClass($nestedPath, 'NestedWarmedEntity.php', 'Articulate\\Tests\\Generated\\Warm' . $suffix . '\\Nested', 'NestedWarmedEntity', true);
        $this->writeFixtureClass($entitiesPath, 'PlainClass.php', 'Articulate\\Tests\\Generated\\Warm' . $suffix, 'PlainClass', false);
        file_put_contents($entitiesPath . '/Ignored.txt', "<?php\n#[\\Articulate\\Attributes\\Entity]\nclass IgnoredTextFile {}\n");

        $warmedClasses = [];
        $metadataRegistry = $this->createMock(EntityMetadataRegistry::class);
        $metadataRegistry->expects($this->exactly(2))
            ->method('getMetadata')
            ->willReturnCallback(function (string $entityClass) use (&$warmedClasses) {
                $warmedClasses[] = $entityClass;

                return $this->createStub(EntityMetadata::class);
            });

        $command = new WarmMetadataCacheCommand($metadataRegistry, [$entitiesPath]);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        sort($warmedClasses);
        $expected = [$entityClass, $nestedEntityClass];
        sort($expected);

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $warmedClasses);
        $this->assertStringContainsString('Warmed metadata cache for 2 entities.', $commandTester->getDisplay());
    }

    public function testWarmsMetadataForEntitiesAcrossMultipleConfiguredDirectories(): void
    {
        $firstPath = $this->tempDir . '/warm_first';
        $secondPath = $this->tempDir . '/warm_second';
        mkdir($firstPath, 0777, true);
        mkdir($secondPath, 0777, true);

        $suffix = str_replace('.', '', uniqid('', true));
        $firstEntityClass = $this->writeFixtureClass($firstPath, 'FirstEntity.php', 'Articulate\\Tests\\Generated\\WarmMulti' . $suffix, 'FirstEntity', true);
        $secondEntityClass = $this->writeFixtureClass($secondPath, 'SecondEntity.php', 'Articulate\\Tests\\Generated\\WarmMulti' . $suffix, 'SecondEntity', true);

        $warmedClasses = [];
        $metadataRegistry = $this->createMock(EntityMetadataRegistry::class);
        $metadataRegistry->expects($this->exactly(2))
            ->method('getMetadata')
            ->willReturnCallback(function (string $entityClass) use (&$warmedClasses) {
                $warmedClasses[] = $entityClass;

                return $this->createStub(EntityMetadata::class);
            });

        $command = new WarmMetadataCacheCommand($metadataRegistry, [$firstPath, $secondPath]);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        sort($warmedClasses);
        $expected = [$firstEntityClass, $secondEntityClass];
        sort($expected);

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $warmedClasses);
        $this->assertStringContainsString('Warmed metadata cache for 2 entities.', $commandTester->getDisplay());
    }

    public function testReportsZeroEntitiesWhenNoneFound(): void
    {
        $entitiesPath = $this->tempDir . '/empty_entities';
        mkdir($entitiesPath, 0777, true);

        $metadataRegistry = $this->createMock(EntityMetadataRegistry::class);
        $metadataRegistry->expects($this->never())->method('getMetadata');

        $command = new WarmMetadataCacheCommand($metadataRegistry, [$entitiesPath]);
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('Warmed metadata cache for 0 entities.', $commandTester->getDisplay());
    }

    public function testThrowsForNonExistentEntitiesPath(): void
    {
        $metadataRegistry = $this->createStub(EntityMetadataRegistry::class);

        $command = new WarmMetadataCacheCommand($metadataRegistry, ['/nonexistent/path/that/does/not/exist']);
        $commandTester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $commandTester->execute([]);
    }
}
