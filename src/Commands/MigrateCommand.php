<?php

namespace Articulate\Commands;

use Articulate\Connection;
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
}
