<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Property;

#[Entity(tableName: 'test_entity')]
class TestSecondEntity
{
    #[Property]
    public string $name;
}
