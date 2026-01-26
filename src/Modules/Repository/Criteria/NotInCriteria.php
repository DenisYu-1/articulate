<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Criteria for NOT IN comparison.
 */
class NotInCriteria implements CriteriaInterface {
    /**
     * @param array<mixed> $values
     */
    public function __construct(
        protected string $field,
        protected array $values
    ) {
    }

    public function apply(QueryBuilder $qb): void
    {
        $qb->whereNotIn($this->field, $this->values);
    }
}
