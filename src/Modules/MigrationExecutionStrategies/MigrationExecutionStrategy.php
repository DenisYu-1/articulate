<?php

namespace Articulate\Modules\MigrationExecutionStrategies;

use Articulate\Connection;
use Articulate\Modules\MigrationsGenerator\BaseMigration;
use Articulate\Utils\MigrationFileUtils;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationExecutionStrategy implements MigrationExecutionStrategyInterface
{
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

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();

                include_once $filePath;

                $className = pathinfo($filePath, PATHINFO_FILENAME);
                $namespace = MigrationFileUtils::getNamespaceFromFile($filePath);
                $fullClassName = $namespace . '\\' . $className;

                if (isset($executedMigrations[$fullClassName])) {
                    continue;
                }

                if (class_exists($fullClassName)) {
                    $migrationInstance = new $fullClassName($this->connection);

                    if (! ($migrationInstance instanceof BaseMigration)) {
                        $io->warning("Class $fullClassName is not a valid migration");
                        continue;
                    }

                    $migrationInstance->runMigration();
                    $io->writeln("Executed migration: $fullClassName");
                    $executedCount++;
                } else {
                    $io->warning("Class $className does not exist in file $filePath");
                }
            }
        }

        if ($executedCount > 0) {
            $io->success("Executed $executedCount migration(s) successfully.");
        } else {
            $io->info('No new migrations to execute.');
        }

        return 0;
    }
}
