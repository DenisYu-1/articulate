<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;

interface MigrationGeneratorStrategy
{
    /**
     * Generate SQL for table operations.
     */
    public function generate(TableCompareResult $compareResult): string;

    /**
     * Generate rollback SQL for table operations.
     */
    public function rollback(TableCompareResult $compareResult): string;

    /**
     * Get the identifier quote character for this database.
     */
    public function getIdentifierQuote(): string;
}
