<?php

namespace Norm\Commands;

use Norm\Attributes\Reflection\ReflectionEntity;
use Norm\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Norm\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Norm\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Norm\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Norm\Modules\MigrationsGenerator\MigrationGenerator;
use Norm\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'norm:diff')]
class DiffCommand extends Command
{
    private readonly MigrationGenerator $migrationGenerator;
    public function __construct(
        private readonly DatabaseSchemaComparator $databaseSchemaComparator,
        private readonly MigrationsCommandGenerator $migrationsCommandGenerator,
    )
    {
        parent::__construct(static::getDefaultName());
        $this->migrationGenerator = new MigrationGenerator( '/app/migrations' );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $classNames = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath('src/Entities')));

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
        $this->migrationGenerator->generate(
            'App\Migrations',
            'MigrationFrom'.time(),
            implode(PHP_EOL, array_map(fn ($query) => '$this->addSql("'.$query.'");', $queries)),
            implode(PHP_EOL, array_map(fn ($query) => '$this->addSql("'.$query.'");', $rollbacks)),
        );

        $io->success('Migrations table created successfully.');

        return Command::SUCCESS;
    }
}
