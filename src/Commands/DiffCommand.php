<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\MigrationsGenerator\MigrationGenerator;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:diff')]
class DiffCommand extends Command
{
    private readonly MigrationGenerator $migrationGenerator;

    public function __construct(
        private readonly DatabaseSchemaComparator $databaseSchemaComparator,
        private readonly MigrationsCommandGenerator $migrationsCommandGenerator,
        private readonly ?string $entitiesPath = null,
        ?string $migrationsPath = null,
        private readonly ?string $migrationsNamespace = null,
    ) {
        parent::__construct();
        $this->migrationGenerator = new MigrationGenerator($migrationsPath ?: '/app/migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $classNames = [];
        $entitiesDir = $this->resolveEntitiesDir();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entitiesDir));

        foreach ($files as $file) {

            if ($file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getRealPath());
                if (preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatches) &&
                    preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
                    $namespace = $namespaceMatches[1];
                    $className = $classMatches[1];
                    $fullClassName = $namespace . '\\' . $className;
                    $classNames[] = $fullClassName;
                }
            }
        }
        $entityClasses = array_map(fn (string $className) => new ReflectionEntity($className), $classNames);

        $compareResults = $this->databaseSchemaComparator->compareAll($entityClasses);
        $queries = $rollbacks = [];
        foreach ($compareResults as $compareResult) {
            $queries[] = $this->migrationsCommandGenerator->generate($compareResult);
            $rollbacks[] = $this->migrationsCommandGenerator->rollback($compareResult);
        }
        $queries = array_values(array_filter($queries));
        $rollbacks = array_values(array_filter($rollbacks));
        if (empty($queries)) {
            $io->success('Schema is already in sync.');

            return Command::SUCCESS;
        }
        $upScript = array_map(fn ($query) => '$this->addSql("' . $query . '");', $queries);
        $downScript = array_map(fn ($query) => '$this->addSql("' . $query . '");', array_reverse($rollbacks));
        $this->migrationGenerator->generate(
            $this->migrationsNamespace ?: 'App\Migrations',
            'MigrationFrom' . time(),
            implode(PHP_EOL, $upScript),
            implode(PHP_EOL, $downScript),
        );

        return Command::SUCCESS;
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



