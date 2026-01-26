<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Interface for criteria that can be applied to a QueryBuilder.
 *
 * Criteria encapsulate query conditions and can be combined logically.
 */
interface CriteriaInterface {
    /**
     * Apply this criteria to the given QueryBuilder.
     *
     * @param QueryBuilder $qb The query builder to apply criteria to
     */
    public function apply(QueryBuilder $qb): void;
}
