<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria for IS NULL comparison.
 */
class IsNullCriteria implements CriteriaInterface {
    public function __construct(
        protected string $field
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        $qb->whereNull($this->field);
    }
}
