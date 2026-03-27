<?php

namespace Articulate\Modules\QueryBuilder\Filter;

use Articulate\Schema\EntityMetadata;

interface QueryFilterInterface {
    public function getCondition(EntityMetadata $metadata): ?string;
}
