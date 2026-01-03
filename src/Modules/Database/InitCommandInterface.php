<?php

namespace Articulate\Modules\Database;

interface InitCommandInterface {
    public function getCreateMigrationsTableSql(): string;
}
