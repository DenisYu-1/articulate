<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for less than comparison.
 */
class LessThanCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return '<';
    }
}
