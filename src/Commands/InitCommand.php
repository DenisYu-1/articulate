<?php

namespace Articulate\Commands;

use Articulate\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:init')]
class InitCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct(static::getDefaultName());
    }

    protected function configure()
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
        $driverName = $this->connection->getDriverName();
        $sql = $this->getCreateTableSql($driverName);
        $this->connection->executeQuery($sql);
    }

    private function getCreateTableSql(string $driverName): string
    {
        return match ($driverName) {
            Connection::MYSQL => "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    executed_at DATETIME NOT NULL,
                    running_time INT NOT NULL
                ) ENGINE=InnoDB;
            ",
            Connection::SQLITE => "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    executed_at TEXT NOT NULL,
                    running_time INT NOT NULL
                );
            ",
            Connection::PGSQL => "
                CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    executed_at TIMESTAMPTZ NOT NULL,
                    running_time INT NOT NULL
                );
            ",
            default => throw new \Exception("Unsupported database driver: $driverName"),
        };
    }
}
