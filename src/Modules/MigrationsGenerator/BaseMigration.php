<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Connection;

abstract class BaseMigration {
    public function __construct(
        private readonly Connection $connection
    ) {}

    public function runMigration(): void
    {
        $this->connection->beginTransaction();
        try {
            $begin = microtime(true);
            $this->up();
            $end = microtime(true);
            $this->addMigration($end - $begin);
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollbackTransaction();
            throw $e;
        }
    }

    public function rollbackMigration(): void
    {
        $this->connection->beginTransaction();
        try {
            $begin = microtime(true);
            $this->down();
            $end = microtime(true);
            $this->removeMigration();
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollbackTransaction();
            throw $e;
        }
    }

    protected function addSql($sqlCommands)
    {
        $this->connection->executeQuery($sqlCommands);
    }

    abstract protected function up(): void;
    protected function down(): void {}

    private function addMigration(float $runningTime)
    {
        $this->connection->executeQuery('
            INSERT INTO migrations (name, executed_at, running_time) 
            VALUES("'.str_replace('\\', '\\\\', static::class).'", "'.date('Y-m-d H:i:s').'", "'.$runningTime.'")
        ');
    }

    private function removeMigration()
    {
        $this->connection->executeQuery('
            DELETE FROM migrations 
            WHERE name = "'.str_replace('\\', '\\\\', static::class).'"
        ');
    }
}
