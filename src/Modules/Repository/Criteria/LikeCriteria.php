<?php

namespace Articulate\Modules\Repository\Criteria;

/**
 * Criteria for LIKE pattern matching.
 */
class LikeCriteria extends ComparisonCriteria {
    protected function getOperator(): string
    {
        return 'LIKE';
    }
}
