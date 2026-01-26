<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for equality comparison.
 */
class EqualsCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return '=';
    }
}
