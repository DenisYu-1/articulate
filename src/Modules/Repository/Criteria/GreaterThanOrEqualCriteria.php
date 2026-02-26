<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for greater than or equal comparison.
 */
class GreaterThanOrEqualCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return '>=';
    }
}
