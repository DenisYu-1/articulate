<?php

namespace Articulate\Modules\Database;

class PostgresqlInitCommand implements InitCommandInterface {
    public function getCreateMigrationsTableSql(): string
    {
        return '
            CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                executed_at TIMESTAMPTZ NOT NULL,
                running_time INT NOT NULL
            );
        ';
    }
}
