<?php

namespace Articulate\Commands;

use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:validate')]
class ValidateCommand extends Command {
    /**
     * @param array<int, string>|null $entitiesPath
     */
    public function __construct(
        private readonly DatabaseSchemaComparator $databaseSchemaComparator,
        private readonly ?array $entitiesPath = null,
        private readonly EntityClassDiscovery $entityClassDiscovery = new EntityClassDiscovery(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Validate that entity mappings are in sync with the database schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $entityClasses = $this->entityClassDiscovery->discover($this->entitiesPath);

        $compareResults = $this->databaseSchemaComparator->compareAll($entityClasses);

        $hasDrift = false;
        $allWarnings = [];

        foreach ($compareResults as $compareResult) {
            $hasDrift = true;
            $allWarnings = array_merge($allWarnings, $compareResult->warnings);
            $io->text(sprintf('[%s] Table "%s" needs %s.', strtoupper($compareResult->operation), $compareResult->name, $compareResult->operation));
        }

        foreach ($allWarnings as $warning) {
            $io->warning($warning);
        }

        if (!$hasDrift && empty($allWarnings)) {
            $io->success('Schema is valid. All entities are in sync with the database.');

            return Command::SUCCESS;
        }

        if (!$hasDrift) {
            $io->caution('Schema is in sync but unmapped required columns were detected (see warnings above).');
        } else {
            $io->error('Schema is out of sync. Run articulate:diff to generate a migration.');
        }

        return Command::FAILURE;
    }
}
