<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Migrations\ExecutionStrategies\MigrationExecutionStrategy;
use Articulate\Modules\Migrations\ExecutionStrategies\RollbackExecutionStrategy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:migrate')]
class MigrateCommand extends Command {
    public function __construct(
        private readonly Connection $connection,
        private readonly InitCommand $initCommand,
        private readonly string $migrationsPath,
        private readonly ?DatabaseSchemaComparator $databaseSchemaComparator = null,
        private readonly ?string $entitiesPath = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run database migrations')
            ->setDefinition(
                new InputDefinition([
                    new InputOption('rollback', null, InputOption::VALUE_NONE, 'Run rollback instead of migration'),
                ])
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isRollback = $input->getOption('rollback');
        $io = new SymfonyStyle($input, $output);

        $this->initCommand->ensureMigrationsTableExists();

        if (!$isRollback) {
            $this->validateSchemaBeforeMigrate();
        }

        $directory = $this->migrationsPath;

        if (!is_dir($directory)) {
            $io->warning("Migrations directory does not exist: $directory");

            return Command::SUCCESS;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $result = $this->connection
            ->executeQuery('SELECT * FROM migrations')
            ->fetchAll();

        $executedMigrations = [];
        foreach ($result as $row) {
            $executedMigrations[$row['name']] = $row;
        }

        $strategy = $isRollback
            ? new RollbackExecutionStrategy($this->connection)
            : new MigrationExecutionStrategy($this->connection);

        return $strategy->execute($io, $executedMigrations, $iterator, $directory);
    }

    private function validateSchemaBeforeMigrate(): void
    {
        if ($this->databaseSchemaComparator === null) {
            return;
        }

        $entitiesDir = $this->resolveEntitiesDir();
        $classNames = [];
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
            if (preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatches)
                && preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
                $namespace = $namespaceMatches[1];
                $className = $classMatches[1];
                $classNames[] = $namespace . '\\' . $className;
            }
        }

        $entityClasses = array_filter(
            array_map(fn (string $className) => new ReflectionEntity($className), $classNames),
            fn (ReflectionEntity $entity) => $entity->isEntity()
        );

        $this->databaseSchemaComparator->compareAll($entityClasses);
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
