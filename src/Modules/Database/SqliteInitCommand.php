<?php

namespace Articulate\Modules\Database;

class SqliteInitCommand implements InitCommandInterface {
    public function getCreateMigrationsTableSql(): string
    {
        return '
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                executed_at TEXT NOT NULL,
                running_time INT NOT NULL
            );
        ';
    }
}
