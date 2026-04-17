<?php

namespace Articulate\Modules\Migrations\ExecutionStrategies;

use Articulate\Connection;
use Articulate\Modules\Migrations\Generator\BaseMigration;
use Articulate\Utils\MigrationFileUtils;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationExecutionStrategy implements MigrationExecutionStrategyInterface {
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
        $executedCount = 0;
        $migrationFiles = [];

        $realDir = realpath($directory);

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $realFile = realpath($file->getPathname());
            if ($realFile === false || !$this->isFileWithinDirectory($realFile, $realDir)) {
                continue;
            }
            $migrationFiles[] = $realFile;
        }

        sort($migrationFiles);

        foreach ($migrationFiles as $filePath) {
            include_once $filePath;

            $className = pathinfo($filePath, PATHINFO_FILENAME);
            $namespace = MigrationFileUtils::getNamespaceFromFile($filePath);
            $fullClassName = $namespace . '\\' . $className;

            if (isset($executedMigrations[$fullClassName])) {
                continue;
            }

            if (!class_exists($fullClassName)) {
                $io->warning("Class $className does not exist in file $filePath");

                continue;
            }

            if (!is_subclass_of($fullClassName, BaseMigration::class)) {
                $io->warning("Class $fullClassName is not a valid migration");

                continue;
            }

            $migrationInstance = new $fullClassName($this->connection);
            $migrationInstance->runMigration();
            $io->writeln("Executed migration: $fullClassName");
            $executedCount++;
        }

        if ($executedCount > 0) {
            $io->success("Executed $executedCount migration(s) successfully.");
        } else {
            $io->info('No new migrations to execute.');
        }

        return 0;
    }

    private function isFileWithinDirectory(string $realFile, string|false $realDir): bool
    {
        return $realDir !== false
            && str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR);
    }
}
