<?php

namespace Articulate\Modules\Migrations\ExecutionStrategies;

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
