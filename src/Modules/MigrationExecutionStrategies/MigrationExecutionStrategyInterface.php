<?php

namespace Articulate\Modules\MigrationExecutionStrategies;

use Symfony\Component\Console\Style\SymfonyStyle;

interface MigrationExecutionStrategyInterface
{
    public function execute(
        SymfonyStyle $io,
        array $executedMigrations,
        \RecursiveIteratorIterator $iterator,
        string $directory
    ): int;
}


