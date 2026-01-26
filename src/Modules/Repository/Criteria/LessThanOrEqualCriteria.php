<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for less than or equal comparison.
 */
class LessThanOrEqualCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return '<=';
    }
}
