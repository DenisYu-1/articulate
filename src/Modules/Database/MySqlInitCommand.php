<?php

namespace Articulate\Modules\Database;

class MySqlInitCommand implements InitCommandInterface {
    public function getCreateMigrationsTableSql(): string
    {
        return '
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                executed_at DATETIME NOT NULL,
                running_time INT NOT NULL
            ) ENGINE=InnoDB;
        ';
    }
}
