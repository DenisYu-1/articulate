<?php

namespace Articulate\Tests\Commands\EntityClassDiscovery;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Commands\EntityClassDiscovery;
use PHPUnit\Framework\TestCase;

class EntityClassDiscoveryTest extends TestCase {
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/articulate_entity_discovery_' . uniqid();
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

    public function testDiscoversEntitiesAcrossMultipleDirectories(): void
    {
        $firstDir = $this->tempDir . '/first';
        $secondDir = $this->tempDir . '/second';
        mkdir($firstDir, 0777, true);
        mkdir($secondDir, 0777, true);

        $suffix = str_replace('.', '', uniqid('', true));
        $firstEntity = $this->writeFixtureClass($firstDir, 'FirstEntity.php', 'Articulate\\Tests\\Generated\\Discovery' . $suffix, 'FirstEntity', true);
        $secondEntity = $this->writeFixtureClass($secondDir, 'SecondEntity.php', 'Articulate\\Tests\\Generated\\Discovery' . $suffix, 'SecondEntity', true);
        $this->writeFixtureClass($secondDir, 'PlainClass.php', 'Articulate\\Tests\\Generated\\Discovery' . $suffix, 'PlainClass', false);

        $discovery = new EntityClassDiscovery();
        $entities = $discovery->discover([$firstDir, $secondDir]);

        $classNames = array_map(fn (ReflectionEntity $entity) => $entity->getName(), $entities);
        sort($classNames);
        $expected = [$firstEntity, $secondEntity];
        sort($expected);

        $this->assertSame($expected, $classNames);
    }

    public function testThrowsNamingTheFailingPathWhenOneOfSeveralDirectoriesIsMissing(): void
    {
        $existingDir = $this->tempDir . '/existing';
        mkdir($existingDir, 0777, true);
        $missingDir = $this->tempDir . '/missing';

        $discovery = new EntityClassDiscovery();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Entities directory not found at configured path: %s', $missingDir));
        $discovery->discover([$existingDir, $missingDir]);
    }

    public function testThrowsWhenGivenAnEmptyArrayOfPaths(): void
    {
        $discovery = new EntityClassDiscovery();

        $this->expectException(\RuntimeException::class);
        $discovery->discover([]);
    }

    public function testAcceptsASinglePathWrappedInAnArray(): void
    {
        $dir = $this->tempDir . '/single';
        mkdir($dir, 0777, true);
        $suffix = str_replace('.', '', uniqid('', true));
        $entityClass = $this->writeFixtureClass($dir, 'SingleEntity.php', 'Articulate\\Tests\\Generated\\Discovery' . $suffix, 'SingleEntity', true);

        $discovery = new EntityClassDiscovery();
        $entities = $discovery->discover([$dir]);

        $this->assertCount(1, $entities);
        $this->assertSame($entityClass, $entities[0]->getName());
    }
}
