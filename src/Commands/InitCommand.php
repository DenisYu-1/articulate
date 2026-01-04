<?php

namespace Articulate\Commands;

use Articulate\Connection;
use Articulate\Modules\Database\InitCommandFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:init')]
class InitCommand extends Command {
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Initialize migrations table in the database')
            ->setHelp('Creates the migrations tracking table if it does not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->ensureMigrationsTableExists();

        $io->success('Migrations table created successfully.');

        return Command::SUCCESS;
    }

    public function ensureMigrationsTableExists(): void
    {
        $initCommand = InitCommandFactory::create($this->connection);
        $sql = $initCommand->getCreateMigrationsTableSql();
        $this->connection->executeQuery($sql);
    }
}
