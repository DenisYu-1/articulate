<?php

namespace Articulate\Modules\Database\SchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\RelationInterface;

interface RelationValidatorInterface
{
    public function validate(RelationInterface $relation): void;

    public function supports(RelationInterface $relation): bool;
}
