<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for greater than comparison.
 */
class GreaterThanCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return '>';
    }
}
