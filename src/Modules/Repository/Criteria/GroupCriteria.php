<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria that groups another criteria.
 */
class GroupCriteria implements CriteriaInterface {
    public function __construct(
        private CriteriaInterface $criteria,
        private string $operator = 'AND'
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        if ($this->operator === 'OR') {
            $qb->orWhere(function($q) {
                $q->apply($this->criteria);
            });
            return;
        }

        $qb->where(function($q) {
            $q->apply($this->criteria);
        });
    }

    public function or(): self
    {
        return new self($this->criteria, 'OR');
    }
}
