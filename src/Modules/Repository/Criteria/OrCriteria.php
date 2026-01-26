<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria that combines multiple criteria with OR logic.
 */
class OrCriteria implements CriteriaInterface {
    /**
     * @param CriteriaInterface[] $criteria
     */
    public function __construct(
        protected array $criteria
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        if ($this->criteria === []) {
            return;
        }

        $first = true;
        foreach ($this->criteria as $criterion) {
            if ($first) {
                $qb->whereGroup($criterion);
                $first = false;
                continue;
            }

            $qb->orWhereGroup($criterion);
        }
    }
}
