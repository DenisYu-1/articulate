<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for NOT LIKE pattern matching.
 */
class NotLikeCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return 'NOT LIKE';
    }
}
