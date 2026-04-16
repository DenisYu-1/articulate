<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:validate')]
class ValidateCommand extends Command {
    public function __construct(
        private readonly DatabaseSchemaComparator $databaseSchemaComparator,
        private readonly ?string $entitiesPath = null,
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

        $classNames = [];
        $entitiesDir = $this->resolveEntitiesDir();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entitiesDir));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getRealPath());
                if ($contents === false) {
                    continue;
                }
                if (preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatches) &&
                    preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
                    $namespace = $namespaceMatches[1];
                    $className = $classMatches[1];
                    $classNames[] = $namespace . '\\' . $className;
                }
            }
        }

        $entityClasses = array_filter(
            array_map(fn (string $className) => new ReflectionEntity($className), $classNames),
            fn (ReflectionEntity $entity) => $entity->isEntity()
        );

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

    private function resolveEntitiesDir(): string
    {
        if ($this->entitiesPath) {
            $resolved = realpath($this->entitiesPath);
            if ($resolved !== false) {
                return $resolved;
            }

            throw new \RuntimeException(sprintf('Entities directory not found at configured path: %s', $this->entitiesPath));
        }

        $defaults = ['src/Entities', 'src/Entity'];
        foreach ($defaults as $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                return $resolved;
            }
        }

        throw new \RuntimeException('Entities directory is not found. Expected one of: src/Entities, src/Entity, or set a custom path.');
    }
}
