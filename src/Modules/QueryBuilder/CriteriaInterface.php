<?php

namespace Articulate\Modules\QueryBuilder;

interface CriteriaInterface {
    public function apply(QueryBuilder $qb): void;
}
