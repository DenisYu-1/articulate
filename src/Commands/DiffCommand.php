<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Migrations\Generator\MigrationGenerator;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:diff')]
class DiffCommand extends Command {
    private readonly MigrationGenerator $migrationGenerator;

    public function __construct(
        private readonly DatabaseSchemaComparator $databaseSchemaComparator,
        private readonly MigrationsCommandGenerator $migrationsCommandGenerator,
        string $migrationsPath,
        private readonly ?string $entitiesPath = null,
        private readonly ?string $migrationsNamespace = null,
    ) {
        parent::__construct();
        $this->migrationGenerator = new MigrationGenerator($migrationsPath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $classNames = [];
        $entitiesDir = $this->resolveEntitiesDir();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entitiesDir));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $realPath = $file->getRealPath();
            if ($realPath === false || !$this->isFileWithinDirectory($realPath, $entitiesDir)) {
                continue;
            }
            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }
            if (preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatches) &&
                preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
                $namespace = $namespaceMatches[1];
                $className = $classMatches[1];
                $fullClassName = $namespace . '\\' . $className;
                $classNames[] = $fullClassName;
            }
        }
        $entityClasses = array_filter(
            array_map(fn (string $className) => new ReflectionEntity($className), $classNames),
            fn (ReflectionEntity $entity) => $entity->isEntity()
        );

        $compareResults = $this->databaseSchemaComparator->compareAll($entityClasses);
        $queries = $rollbacks = [];
        $allWarnings = [];
        foreach ($compareResults as $compareResult) {
            $allWarnings = array_merge($allWarnings, $compareResult->warnings);
            $queries[] = $this->migrationsCommandGenerator->generate($compareResult);
            $rollbacks[] = $this->migrationsCommandGenerator->rollback($compareResult);
        }
        foreach ($allWarnings as $warning) {
            $io->warning($warning);
        }
        $queries = array_values(array_filter($queries));
        $rollbacks = array_values(array_filter($rollbacks));
        if (empty($queries)) {
            $io->success('Schema is already in sync.');

            return Command::SUCCESS;
        }
        $escapeSql = fn (string $query) => addcslashes($query, '"\\');
        $upScript = array_map(fn ($query) => '$this->addSql("' . $escapeSql($query) . '");', $queries);
        $downScript = array_map(fn ($query) => '$this->addSql("' . $escapeSql($query) . '");', array_reverse($rollbacks));
        $this->migrationGenerator->generate(
            $this->migrationsNamespace ?: 'App\Migrations',
            'MigrationFrom' . time(),
            implode(PHP_EOL, $upScript),
            implode(PHP_EOL, $downScript),
        );

        return Command::SUCCESS;
    }

    private function isFileWithinDirectory(string $realPath, string $baseDir): bool
    {
        return str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR);
    }

    private function resolveEntitiesDir(): string
    {
        if ($this->entitiesPath) {
            $resolved = realpath($this->entitiesPath);
            if ($resolved !== false) {
                return $resolved;
            }

            throw new \RuntimeException(sprintf('Entities directory not found at configured path: %s', $this->entitiesPath));
        }

        $defaults = ['src/Entities', 'src/Entity'];
        foreach ($defaults as $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                return $resolved;
            }
        }

        throw new \RuntimeException('Entities directory is not found. Expected one of: src/Entities, src/Entity, or set a custom path.');
    }
}
