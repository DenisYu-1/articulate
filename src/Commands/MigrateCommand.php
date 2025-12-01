<?php

namespace Norm\Commands;

use Norm\Connection;
use Norm\Modules\MigrationsGenerator\BaseMigration;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'norm:migrate')]
class MigrateCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    )
    {
        parent::__construct(static::getDefaultName());
    }

    protected function configure()
    {
        $this
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

        $classNames = [];

        $directory = '/app/migrations';

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

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();

                // Include the file (assuming it contains a class definition)
                include_once $filePath;

                // Extract the class name from the file path (assuming file name matches class name)
                $className = pathinfo($filePath, PATHINFO_FILENAME);

                $namespace = getNamespaceFromFile($filePath);
                $fullClassName = $namespace . '\\' . $className;
                if (!$isRollback && isset($executedMigrations[$fullClassName])) {
                    continue;
                } elseif ($isRollback && !isset($executedMigrations[$fullClassName])) {
                    continue;
                }
                // Instantiate the class
                if (class_exists($fullClassName)) {
                    $migrationInstance = new $fullClassName($this->connection);

                    /** @var BaseMigration $migrationInstance */
                    if (!$isRollback && $migrationInstance instanceof BaseMigration) {
                        $migrationInstance->runMigration();
                    }
                } else {
                    echo "Class $className does not exist in file $filePath" . PHP_EOL;
                }
            }
        }

        if ($migrationInstance && $isRollback) {
            $migrationInstance->rollbackMigration();
        }

        /**
         * читаем из бд миграции
         * сканируем папку
         * идем по файлам
         * если есть в базе – пропускаем
         * иначе делаем ап, добавляем в бд
         *
         */

        $io->success('Migrations table created successfully.');

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
