<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;

interface MigrationGeneratorInterface {
    public function getIdentifierQuote(): string;

    /**
     * @return string[]
     */
    public function generate(TableCompareResult $compareResult): array;

    /**
     * @return string[]
     */
    public function rollback(TableCompareResult $compareResult): array;
}
