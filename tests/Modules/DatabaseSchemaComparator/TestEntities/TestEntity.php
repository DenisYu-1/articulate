<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Property;

#[Entity]
class TestEntity
{
    #[Property]
    public int $id;
}
