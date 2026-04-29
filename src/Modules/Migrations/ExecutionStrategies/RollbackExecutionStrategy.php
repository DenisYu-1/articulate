<?php

namespace Articulate\Modules\Migrations\ExecutionStrategies;

use Articulate\Connection;
use Articulate\Modules\Migrations\Generator\BaseMigration;
use Articulate\Utils\MigrationFileUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

class RollbackExecutionStrategy implements MigrationExecutionStrategyInterface {
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function execute(
        SymfonyStyle $io,
        array $executedMigrations,
        \RecursiveIteratorIterator $iterator,
        string $directory
    ): int {
        // Find the latest executed migration from database
        $latestMigration = $this->connection
            ->executeQuery('SELECT name FROM migrations ORDER BY id DESC LIMIT 1')
            ->fetch();

        if (!$latestMigration) {
            $io->info('No migrations to rollback.');

            return Command::SUCCESS;
        }

        $migrationToRollback = $latestMigration['name'];

        // Find the migration file
        $migrationFileFound = false;
        $migrationInstance = null;

        $realDir = realpath($directory);

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $realFile = realpath($file->getPathname());
            if ($realFile === false || !$this->isFileWithinDirectory($realFile, $realDir)) {
                continue;
            }
            $filePath = $realFile;

            include_once $filePath;

            $className = pathinfo($filePath, PATHINFO_FILENAME);
            $namespace = MigrationFileUtils::getNamespaceFromFile($filePath);
            $fullClassName = $namespace . '\\' . $className;

            if ($fullClassName !== $migrationToRollback) {
                continue;
            }

            if (!class_exists($fullClassName)) {
                continue;
            }

            if (!is_subclass_of($fullClassName, BaseMigration::class)) {
                $io->warning("Class $fullClassName is not a valid migration");

                return Command::FAILURE;
            }

            $migrationInstance = new $fullClassName($this->connection);
            $migrationFileFound = true;

            break;
        }

        if (!$migrationFileFound || !$migrationInstance) {
            $io->warning("Migration file for $migrationToRollback not found");

            return Command::FAILURE;
        }

        $migrationInstance->rollbackMigration();
        $io->success("Migration $migrationToRollback rolled back successfully.");

        return Command::SUCCESS;
    }

    private function isFileWithinDirectory(string $realFile, string|false $realDir): bool
    {
        return $realDir !== false
            && str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR);
    }
}
