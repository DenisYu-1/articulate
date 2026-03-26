<?php

namespace Articulate\Modules\Repository\Criteria;

use Articulate\Modules\QueryBuilder\QueryBuilder;
use InvalidArgumentException;

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
     *
     * @note Field names are interpolated and should come from trusted metadata mappings.
     */
    public function apply(QueryBuilder $qb): void
    {
        if ($this->value === null) {
            $this->applyNullComparison($qb);

            return;
        }

        if ($this->operator === 'OR') {
            $qb->orWhere("{$this->field} {$this->getOperator()} {$this->getPlaceholder()}", $this->value);
        } else {
            $qb->where("{$this->field} {$this->getOperator()} {$this->getPlaceholder()}", $this->value);
        }
    }

    private function applyNullComparison(QueryBuilder $qb): void
    {
        $operator = strtolower(trim($this->getOperator()));
        $condition = $this->buildNullCondition($operator);

        if ($this->operator === 'OR') {
            $qb->orWhere($condition);
        } else {
            $qb->where($condition);
        }
    }

    private function buildNullCondition(string $operator): string
    {
        return match ($operator) {
            '=', 'eq' => "{$this->field} IS NULL",
            '!=', '<>', 'ne' => "{$this->field} IS NOT NULL",
            default => throw new InvalidArgumentException("Unsupported null comparison operator: {$operator}"),
        };
    }
}
