<?php

namespace Articulate\Commands;

use Articulate\Connection;
use Articulate\Modules\MigrationExecutionStrategies\MigrationExecutionStrategy;
use Articulate\Modules\MigrationExecutionStrategies\MigrationExecutionStrategyInterface;
use Articulate\Modules\MigrationExecutionStrategies\RollbackExecutionStrategy;
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

        $strategy = $isRollback
            ? new RollbackExecutionStrategy($this->connection)
            : new MigrationExecutionStrategy($this->connection);

        return $strategy->execute($io, $executedMigrations, $iterator, $directory);
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
