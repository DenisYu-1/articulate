<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
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
    ) {
        parent::__construct();
        $this->migrationGenerator = new MigrationGenerator('/app/migrations');
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
        $entityClasses = array_map(fn(string $className) => new ReflectionEntity($className), $classNames);

        $compareResults = $this->databaseSchemaComparator->compareAll($entityClasses);
        $queries = $rollbacks = [];
        $io->writeln('Entities: ');
        foreach ($compareResults as $compareResult) {
            $io->writeln($compareResult->name);

            $io->writeln('Should be: ' . match ($compareResult->operation) {
                    CompareResult::OPERATION_CREATE => 'created',
                    CompareResult::OPERATION_UPDATE => 'altered',
                    CompareResult::OPERATION_DELETE => 'dropped',
                });

            $io->writeln(' Columns:');

            foreach ($compareResult->columns as $columnInfo) {
                $io->writeln('name: ' . $columnInfo->name);
                $io->writeln('  operation: ' . $columnInfo->operation);

                $io->writeln('  type: ' . ($columnInfo->typeMatch ? 'match' : 'not_match'));
                $io->writeln('  nullable: ' . ($columnInfo->isNullableMatch ? 'match' : 'not_match'));
            }

            $io->writeln(' Indexes:');

            foreach ($compareResult->indexes as $indexInfo) {
                $io->writeln('name: ' . $indexInfo->name);
                $io->writeln('  operation: ' . $indexInfo->operation);
            }
            $queries[] = $this->migrationsCommandGenerator->generate($compareResult);
            $rollbacks[] = $this->migrationsCommandGenerator->rollback($compareResult);
        }
        $upScript = array_map(fn ($query) => '$this->addSql("'.$query.'");', $queries);
        $downScript = array_map(fn ($query) => '$this->addSql("'.$query.'");', array_reverse($rollbacks));
        $this->migrationGenerator->generate(
            'App\Migrations',
            'MigrationFrom'.time(),
            implode(PHP_EOL, $upScript),
            implode(PHP_EOL, $downScript),
        );

        $io->success('Migrations table created successfully.');

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
