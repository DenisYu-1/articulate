<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria that combines multiple criteria with AND logic.
 */
class AndCriteria implements CriteriaInterface {
    /**
     * @param CriteriaInterface[] $criteria
     */
    public function __construct(
        protected array $criteria
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        foreach ($this->criteria as $criterion) {
            $criterion->apply($qb);
        }
    }
}
