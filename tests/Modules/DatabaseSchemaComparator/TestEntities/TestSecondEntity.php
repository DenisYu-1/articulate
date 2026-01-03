<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_entity')]
class TestSecondEntity {
    #[Property]
    public string $name;
}
