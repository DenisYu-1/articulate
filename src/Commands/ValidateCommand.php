<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Schema\EntityMetadataRegistry;
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
        private readonly EntityMetadataRegistry $metadataRegistry = new EntityMetadataRegistry(),
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

        $hasVersionErrors = $this->validateVersionColumns($entityClasses, $io);

        if (!$hasDrift && empty($allWarnings) && !$hasVersionErrors) {
            $io->success('Schema is valid. All entities are in sync with the database.');

            return Command::SUCCESS;
        }

        if (!$hasDrift) {
            $io->caution('Schema is in sync but unmapped required columns were detected (see warnings above).');
        } else {
            $io->error('Schema is out of sync. Run articulate:diff to generate a migration.');
        }

        if ($hasVersionErrors) {
            $io->error('Optimistic-locking version-column coverage errors found (see above).');
        }

        return Command::FAILURE;
    }

    /**
     * Every entity class mapping a versioned table must account for every #[Version]
     * column on that table — either as its own #[Version] property or listed in its
     * own #[VersionAware] — so a write through that class is never invisible to a
     * checking sibling's lost-update detection.
     *
     * @param ReflectionEntity[] $entityClasses
     */
    private function validateVersionColumns(array $entityClasses, SymfonyStyle $io): bool
    {
        $hasError = false;
        $metadataByTable = [];

        foreach ($entityClasses as $reflectionEntity) {
            $metadata = $this->metadataRegistry->getMetadata($reflectionEntity->getName());
            $metadataByTable[$metadata->getTableName()][] = $metadata;
        }

        foreach ($metadataByTable as $tableName => $metadataGroup) {
            $canonicalVersionColumns = $this->metadataRegistry->getVersionColumnsForTable($tableName);
            $anyVersionColumns = array_merge($canonicalVersionColumns, ...array_map(
                fn ($metadata) => $metadata->getVersionColumns(),
                $metadataGroup
            ));

            if ($anyVersionColumns === []) {
                continue;
            }

            if (count($canonicalVersionColumns) > 1) {
                $io->info(sprintf(
                    'Table "%s" has multiple distinct #[Version] columns (%s) across its entity classes.',
                    $tableName,
                    implode(', ', $canonicalVersionColumns)
                ));
            }

            foreach ($metadataGroup as $metadata) {
                $classVersionColumns = $metadata->getVersionColumns();

                foreach (array_diff($canonicalVersionColumns, $classVersionColumns) as $missingColumn) {
                    $hasError = true;
                    $io->error(sprintf(
                        'Class "%s" does not account for version column "%s" on table "%s".',
                        $metadata->getClassName(),
                        $missingColumn,
                        $tableName
                    ));
                }

                $ownAwareColumns = array_diff($classVersionColumns, $metadata->getCheckedVersionColumns());
                foreach (array_diff($ownAwareColumns, $canonicalVersionColumns) as $danglingColumn) {
                    $hasError = true;
                    $io->error(sprintf(
                        'Class "%s" declares #[VersionAware] column "%s" on table "%s" with no canonical #[Version] owner in the group.',
                        $metadata->getClassName(),
                        $danglingColumn,
                        $tableName
                    ));
                }
            }
        }

        return $hasError;
    }
}
