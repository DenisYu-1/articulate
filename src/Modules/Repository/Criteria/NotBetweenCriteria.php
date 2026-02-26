<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria for NOT BETWEEN comparison.
 */
class NotBetweenCriteria implements CriteriaInterface {
    public function __construct(
        protected string $field,
        protected mixed $min,
        protected mixed $max
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        $qb->where("{$this->field} NOT BETWEEN ? AND ?", [$this->min, $this->max]);
    }
}
