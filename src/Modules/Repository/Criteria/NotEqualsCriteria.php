<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for inequality comparison.
 */
class NotEqualsCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return '!=';
    }
}
