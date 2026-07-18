<?php

namespace Articulate\Commands;

use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Migrations\Generator\MigrationGenerator;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:diff')]
class DiffCommand extends Command {
    private readonly MigrationGenerator $migrationGenerator;

    /**
     * @param array<int, string>|null $entitiesPath
     */
    public function __construct(
        private readonly DatabaseSchemaComparator $databaseSchemaComparator,
        private readonly MigrationsCommandGenerator $migrationsCommandGenerator,
        string $migrationsPath,
        private readonly ?array $entitiesPath = null,
        private readonly ?string $migrationsNamespace = null,
        private readonly EntityClassDiscovery $entityClassDiscovery = new EntityClassDiscovery(),
    ) {
        parent::__construct();
        $this->migrationGenerator = new MigrationGenerator($migrationsPath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $entityClasses = $this->entityClassDiscovery->discover($this->entitiesPath);

        $compareResults = $this->databaseSchemaComparator->compareAll($entityClasses);
        $queries = $rollbacks = [];
        $allWarnings = [];
        foreach ($compareResults as $compareResult) {
            $allWarnings = array_merge($allWarnings, $compareResult->warnings);
            array_push($queries, ...$this->migrationsCommandGenerator->generate($compareResult));
            array_push($rollbacks, ...$this->migrationsCommandGenerator->rollback($compareResult));
        }
        foreach ($allWarnings as $warning) {
            $io->warning($warning);
        }
        $queries = array_values(array_filter($queries));
        $rollbacks = array_values(array_filter($rollbacks));
        if (empty($queries)) {
            $io->success('Schema is already in sync.');

            return Command::SUCCESS;
        }
        $upScript = array_map(fn ($query) => '$this->addSql(' . $this->phpStringLiteral($query) . ');', $queries);
        $downScript = array_map(fn ($query) => '$this->addSql(' . $this->phpStringLiteral($query) . ');', array_reverse($rollbacks));
        $this->migrationGenerator->generate(
            $this->migrationsNamespace ?: 'App\Migrations',
            'MigrationFrom' . time(),
            implode(PHP_EOL, $upScript),
            implode(PHP_EOL, $downScript),
        );

        return Command::SUCCESS;
    }

    private function phpStringLiteral(string $value): string
    {
        return var_export($value, true);
    }
}
