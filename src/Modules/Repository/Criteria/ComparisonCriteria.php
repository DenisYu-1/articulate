<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;

/**
 * Base class for comparison-based criteria.
 *
 * Provides common functionality for criteria that compare a field against a value.
 */
abstract class ComparisonCriteria implements CriteriaInterface {
    public function __construct(
        protected string $field,
        protected mixed $value,
        protected string $operator = 'AND'
    ) {
    }

    /**
     * Get the SQL operator for this comparison.
     */
    abstract protected function getOperator(): string;

    /**
     * Get the placeholder for this comparison.
     */
    protected function getPlaceholder(): string
    {
        return '?';
    }

    /**
     * Apply this criteria to the query builder.
     */
    public function apply(QueryBuilder $qb): void
    {
        if ($this->operator === 'OR') {
            $qb->orWhere("{$this->field} {$this->getOperator()} {$this->getPlaceholder()}", $this->value);
        } else {
            $qb->where("{$this->field} {$this->getOperator()} {$this->getPlaceholder()}", $this->value);
        }
    }
}
