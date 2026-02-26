<?php

namespace Articulate\Modules\QueryBuilder\Filter;

use Articulate\Modules\EntityManager\EntityMetadata;

interface QueryFilterInterface {
    public function getCondition(EntityMetadata $metadata): ?string;
}
