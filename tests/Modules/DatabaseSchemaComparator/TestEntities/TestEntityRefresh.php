<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_entity_refresh')]
class TestEntityRefresh {
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[Property]
    public string $name;
}
