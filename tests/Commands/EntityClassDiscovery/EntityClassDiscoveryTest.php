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

    public function testDiscoversMultipleEntityClassesDeclaredInOneFile(): void
    {
        $dir = $this->tempDir . '/multi';
        mkdir($dir, 0777, true);
        $namespace = 'Articulate\\Tests\\Generated\\Discovery' . str_replace('.', '', uniqid('', true));
        $path = $dir . '/Multi.php';
        file_put_contents($path, <<<PHP
<?php

namespace {$namespace};

#[\\Articulate\\Attributes\\Entity]
class FirstInFile {
}

#[\\Articulate\\Attributes\\Entity]
class SecondInFile {
}
PHP);
        require_once $path;

        $discovery = new EntityClassDiscovery();
        $entities = $discovery->discover([$dir]);

        $classNames = array_map(fn (ReflectionEntity $entity) => $entity->getName(), $entities);
        sort($classNames);
        $expected = [$namespace . '\\FirstInFile', $namespace . '\\SecondInFile'];
        sort($expected);

        $this->assertSame($expected, $classNames);
    }

    public function testIgnoresAnonymousClassesAndStillFindsTheNamedEntityInTheSameFile(): void
    {
        $dir = $this->tempDir . '/anon';
        mkdir($dir, 0777, true);
        $namespace = 'Articulate\\Tests\\Generated\\Discovery' . str_replace('.', '', uniqid('', true));
        $path = $dir . '/WithAnonymous.php';
        file_put_contents($path, <<<PHP
<?php

namespace {$namespace};

\$anonymous = new class {
    public int \$value = 1;
};

#[\\Articulate\\Attributes\\Entity]
class NamedEntity {
}
PHP);
        require_once $path;

        $discovery = new EntityClassDiscovery();
        $entities = $discovery->discover([$dir]);

        $this->assertCount(1, $entities);
        $this->assertSame($namespace . '\\NamedEntity', $entities[0]->getName());
    }

    public function testIgnoresClassNamesMentionedInCommentsOrDocblocksBeforeTheRealDeclaration(): void
    {
        $dir = $this->tempDir . '/comment';
        mkdir($dir, 0777, true);
        $namespace = 'Articulate\\Tests\\Generated\\Discovery' . str_replace('.', '', uniqid('', true));
        $path = $dir . '/Commented.php';
        file_put_contents($path, <<<PHP
<?php

namespace {$namespace};

// Not a real declaration: class DecoyOne {}
/**
 * See also class DecoyTwo for context.
 */
#[\\Articulate\\Attributes\\Entity]
class RealEntity {
}
PHP);
        require_once $path;

        $discovery = new EntityClassDiscovery();
        $entities = $discovery->discover([$dir]);

        $this->assertCount(1, $entities);
        $this->assertSame($namespace . '\\RealEntity', $entities[0]->getName());
    }

    public function testSkipsClassesThatWereNeverLoadedWithoutThrowing(): void
    {
        $dir = $this->tempDir . '/unloaded';
        mkdir($dir, 0777, true);
        $namespace = 'Articulate\\Tests\\Generated\\Discovery' . str_replace('.', '', uniqid('', true));

        // Written but deliberately never require_once'd, and not reachable by any
        // autoloader, so the class never actually gets declared.
        file_put_contents($dir . '/NeverLoaded.php', <<<PHP
<?php

namespace {$namespace};

#[\\Articulate\\Attributes\\Entity]
class NeverLoaded {
}
PHP);
        $loadedEntity = $this->writeFixtureClass($dir, 'Loaded.php', $namespace, 'Loaded', true);

        $discovery = new EntityClassDiscovery();
        $entities = $discovery->discover([$dir]);

        $classNames = array_map(fn (ReflectionEntity $entity) => $entity->getName(), $entities);
        $this->assertSame([$loadedEntity], $classNames);
    }
}
