<?php

namespace Articulate\Tests\Utils;

use Articulate\Utils\MigrationFileUtils;
use PHPUnit\Framework\TestCase;

class MigrationFileUtilsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/articulate_migration_test_' . uniqid();
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

    public function testGetNamespaceFromFileWithValidNamespace(): void
    {
        $fileContent = <<<PHP
<?php

namespace My\Custom\Migrations;

use Doctrine\DBAL\Schema\Schema;

class TestMigration
{
    // Migration code here
}
PHP;

        $filePath = $this->tempDir . '/TestMigration.php';
        file_put_contents($filePath, $fileContent);

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('My\Custom\Migrations', $result);
    }

    public function testGetNamespaceFromFileWithNamespaceAtEnd(): void
    {
        $fileContent = <<<PHP
<?php
declare(strict_types=1);

use Some\Other\Class;
use Another\Class;

namespace App\Migrations\Version2023;
PHP;

        $filePath = $this->tempDir . '/Migration.php';
        file_put_contents($filePath, $fileContent);

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('App\Migrations\Version2023', $result);
    }

    public function testGetNamespaceFromFileWithNoNamespace(): void
    {
        $fileContent = <<<PHP
<?php

class TestClass
{
    // No namespace
}
PHP;

        $filePath = $this->tempDir . '/NoNamespace.php';
        file_put_contents($filePath, $fileContent);

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertNull($result);
    }

    public function testGetNamespaceFromFileWithEmptyFile(): void
    {
        $filePath = $this->tempDir . '/Empty.php';
        file_put_contents($filePath, '');

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertNull($result);
    }

    public function testGetNamespaceFromFileWithNamespaceOnFirstLine(): void
    {
        $fileContent = <<<PHP
<?php
namespace SingleLine;
class Test {}
PHP;

        $filePath = $this->tempDir . '/SingleLine.php';
        file_put_contents($filePath, $fileContent);

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('SingleLine', $result);
    }

    public function testGetNamespaceFromFileWithCommentsBeforeNamespace(): void
    {
        $fileContent = <<<PHP
<?php

/*
 * This is a comment
 * that spans multiple lines
 */

// Another comment
# Shell style comment

namespace Commented\Namespace\Migrations;
PHP;

        $filePath = $this->tempDir . '/Commented.php';
        file_put_contents($filePath, $fileContent);

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('Commented\Namespace\Migrations', $result);
    }

    public function testGetNamespaceFromFileWithComplexNamespace(): void
    {
        $fileContent = <<<PHP
<?php

namespace Very\Long\Namespace\Path\For\Testing\Purposes\In\This\Unit\Test;

class ComplexMigration
{
}
PHP;

        $filePath = $this->tempDir . '/Complex.php';
        file_put_contents($filePath, $fileContent);

        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('Very\Long\Namespace\Path\For\Testing\Purposes\In\This\Unit\Test', $result);
    }

    public function testGetNamespaceFromFileWithInvalidFilePath(): void
    {
        // Suppress the warning for nonexistent file
        $result = @MigrationFileUtils::getNamespaceFromFile('/nonexistent/file.php');

        $this->assertNull($result);
    }

    public function testGetNamespaceFromFileWithNonPHPExtension(): void
    {
        $fileContent = <<<PHP
<?php

namespace Test\Namespace;

class Test {}
PHP;

        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, $fileContent);

        // The method doesn't check file extension, so it should still work
        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('Test\Namespace', $result);
    }

    public function testGetNamespaceFromFileWithNamespaceAfterClass(): void
    {
        $fileContent = <<<PHP
<?php

class TestClass
{
}

namespace Late\Namespace;
PHP;

        $filePath = $this->tempDir . '/LateNamespace.php';
        file_put_contents($filePath, $fileContent);

        // The method reads line by line and returns the first namespace found
        $result = MigrationFileUtils::getNamespaceFromFile($filePath);

        $this->assertSame('Late\Namespace', $result);
    }
}