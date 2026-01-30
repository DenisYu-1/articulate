<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria that negates another criteria.
 */
class NotCriteria implements CriteriaInterface {
    public function __construct(
        protected CriteriaInterface $criteria
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        $qb->whereNot(function($q) {
            $q->apply($this->criteria);
        });
    }
}
