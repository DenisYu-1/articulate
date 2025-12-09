<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;

#[Entity]
class TestOneToManyWrongOwner
{
    #[Property]
    public int $id;

    #[Property]
    public int $target;
}

