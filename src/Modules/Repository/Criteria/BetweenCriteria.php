<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria for BETWEEN comparison.
 */
class BetweenCriteria implements CriteriaInterface {
    public function __construct(
        protected string $field,
        protected mixed $min,
        protected mixed $max
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        $qb->whereBetween($this->field, $this->min, $this->max);
    }
}
