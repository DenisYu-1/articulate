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
            ->setDescription('Outputs a hello message.')
            ->setHelp('This command allows you to print a hello message...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $driverName = $this->connection->getDriverName();

        $sql = $this->getCreateTableSql($driverName);

        $this->connection->executeQuery($sql);

        $io->success('Migrations table created successfully.');

        return Command::SUCCESS;
    }

    private function getCreateTableSql(string $driverName)
    {
        switch ($driverName) {
            case Connection::MYSQL:
                return "
                    CREATE TABLE IF NOT EXISTS migrations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        executed_at DATETIME NOT NULL,
                        running_time INT NOT NULL
                    ) ENGINE=InnoDB;
                ";
            case Connection::SQLITE:
                return "
                    CREATE TABLE IF NOT EXISTS migrations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        executed_at TEXT NOT NULL,
                        running_time INT NOT NULL
                    );
                ";
            case Connection::PGSQL:
                return "
                    CREATE TABLE IF NOT EXISTS migrations (
                        id SERIAL PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        executed_at TIMESTAMPTZ NOT NULL,
                        running_time INT NOT NULL
                    );
                ";
            default:
                throw new \Exception("Unsupported database driver: $driverName");
        }
    }
}
