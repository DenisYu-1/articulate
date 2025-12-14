<?php

namespace Articulate\Commands;

use Articulate\Connection;
use Articulate\Modules\MigrationsGenerator\BaseMigration;
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
    ) {
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

        if (! is_dir($directory)) {
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

        if ($isRollback) {
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

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $filePath = $file->getPathname();

                    include_once $filePath;

                    $className = pathinfo($filePath, PATHINFO_FILENAME);
                    $namespace = getNamespaceFromFile($filePath);
                    $fullClassName = $namespace . '\\' . $className;

                    if ($fullClassName === $migrationToRollback && class_exists($fullClassName)) {
                        $migrationInstance = new $fullClassName($this->connection);

                        if ($migrationInstance instanceof BaseMigration) {
                            $migrationFileFound = true;
                            break;
                        } else {
                            $io->warning("Class $fullClassName is not a valid migration");
                            return Command::FAILURE;
                        }
                    }
                }
            }

            if (!$migrationFileFound || !$migrationInstance) {
                $io->warning("Migration file for $migrationToRollback not found");
                return Command::FAILURE;
            }

            $migrationInstance->rollbackMigration();
            $io->success("Migration $migrationToRollback rolled back successfully.");
        } else {
            // Regular migration execution
            $executedCount = 0;

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $filePath = $file->getPathname();

                    include_once $filePath;

                    $className = pathinfo($filePath, PATHINFO_FILENAME);
                    $namespace = getNamespaceFromFile($filePath);
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
        }

        return Command::SUCCESS;
    }
}

function getNamespaceFromFile(string $filePath): ?string
{
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
