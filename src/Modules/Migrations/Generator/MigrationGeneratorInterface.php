<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;

interface MigrationGeneratorInterface {
    public function getIdentifierQuote(): string;

    public function generate(TableCompareResult $compareResult): string;

    public function rollback(TableCompareResult $compareResult): string;
}
