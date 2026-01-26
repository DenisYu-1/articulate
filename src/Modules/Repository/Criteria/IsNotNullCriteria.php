<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria for IS NOT NULL comparison.
 */
class IsNotNullCriteria implements CriteriaInterface {
    public function __construct(
        protected string $field
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        $qb->whereNotNull($this->field);
    }
}
