<?php

namespace Articulate\Modules\QueryBuilder\Filter;

use Articulate\Modules\EntityManager\EntityMetadata;

class SoftDeleteFilter implements QueryFilterInterface {
    public function getCondition(EntityMetadata $metadata): ?string
    {
        if (!$metadata->isSoftDeleteable()) {
            return null;
        }

        $softDeleteColumn = $metadata->getSoftDeleteColumn();
        if ($softDeleteColumn === null) {
            return null;
        }

        return "{$softDeleteColumn} IS NULL";
    }
}
