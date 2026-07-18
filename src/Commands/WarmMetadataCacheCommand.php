<?php

namespace Articulate\Commands;

use Articulate\Schema\EntityMetadataRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:warm-metadata-cache')]
class WarmMetadataCacheCommand extends Command {
    /**
     * @param array<int, string>|null $entitiesPath
     */
    public function __construct(
        private readonly EntityMetadataRegistry $metadataRegistry,
        private readonly ?array $entitiesPath = null,
        private readonly EntityClassDiscovery $entityClassDiscovery = new EntityClassDiscovery(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Warm the entity metadata cache so the first request does not pay for it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $entities = $this->entityClassDiscovery->discover($this->entitiesPath);

        foreach ($entities as $entity) {
            $this->metadataRegistry->getMetadata($entity->getName());
        }

        $io->success(sprintf('Warmed metadata cache for %d entities.', count($entities)));

        return Command::SUCCESS;
    }
}
