<?php

namespace Articulate\Commands;

use Articulate\Connection;
use Articulate\Modules\MigrationsGenerator\BaseMigration;
use Articulate\Commands\InitCommand;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:migrate')]
class MigrateCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly InitCommand $initCommand,
        private readonly ?string $migrationsPath = null,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run database migrations')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('rollback', InputArgument::OPTIONAL),
                ])
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isRollback = $input->getArgument('rollback') === 'rollback';
        $io = new SymfonyStyle($input, $output);

        $this->initCommand->ensureMigrationsTableExists();

        $directory = $this->migrationsPath ?: '/app/migrations';

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

        $executedCount = 0;
        $lastMigrationInstance = null;

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();

                include_once $filePath;

                $className = pathinfo($filePath, PATHINFO_FILENAME);
                $namespace = getNamespaceFromFile($filePath);
                $fullClassName = $namespace . '\\' . $className;

                if (!$isRollback && isset($executedMigrations[$fullClassName])) {
                    continue;
                } elseif ($isRollback && !isset($executedMigrations[$fullClassName])) {
                    continue;
                }

                if (class_exists($fullClassName)) {
                    $migrationInstance = new $fullClassName($this->connection);

                    if (!($migrationInstance instanceof BaseMigration)) {
                        $io->warning("Class $fullClassName is not a valid migration");
                        continue;
                    }

                    if (!$isRollback) {
                        $migrationInstance->runMigration();
                        $io->writeln("Executed migration: $fullClassName");
                        $executedCount++;
                    } else {
                        $lastMigrationInstance = $migrationInstance;
                    }
                } else {
                    $io->warning("Class $className does not exist in file $filePath");
                }
            }
        }

        if ($isRollback && $lastMigrationInstance) {
            $lastMigrationInstance->rollbackMigration();
            $io->success('Last migration rolled back successfully.');
        } elseif ($isRollback) {
            $io->info('No migrations to rollback.');
        } elseif ($executedCount > 0) {
            $io->success("Executed $executedCount migration(s) successfully.");
        } else {
            $io->info('No new migrations to execute.');
        }

        return Command::SUCCESS;
    }
}

function getNamespaceFromFile(string $filePath): ?string {
    $namespace = null;
    $handle = fopen($filePath, 'r');

    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^namespace\s+(.+?);$/', trim($line), $matches)) {
                $namespace = $matches[1];
                break;
            }
        }
        fclose($handle);
    }

    return $namespace;
}
