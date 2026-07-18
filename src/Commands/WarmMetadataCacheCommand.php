<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Schema\EntityMetadataRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'articulate:warm-metadata-cache')]
class WarmMetadataCacheCommand extends Command {
    public function __construct(
        private readonly EntityMetadataRegistry $metadataRegistry,
        private readonly ?string $entitiesPath = null,
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

        $classNames = [];
        $entitiesDir = $this->resolveEntitiesDir();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entitiesDir));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $realPath = $file->getRealPath();
            if ($realPath === false || !$this->isFileWithinDirectory($realPath, $entitiesDir)) {
                continue;
            }
            $contents = file_get_contents($realPath);
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

        $entityClasses = array_filter(
            $classNames,
            fn (string $className) => (new ReflectionEntity($className))->isEntity()
        );

        foreach ($entityClasses as $entityClass) {
            $this->metadataRegistry->getMetadata($entityClass);
        }

        $io->success(sprintf('Warmed metadata cache for %d entities.', count($entityClasses)));

        return Command::SUCCESS;
    }

    private function isFileWithinDirectory(string $realPath, string $baseDir): bool
    {
        return str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR);
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
