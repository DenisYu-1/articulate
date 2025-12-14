<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_entity3')]
class TestPrimaryKeyEntity
{
    #[PrimaryKey]
    #[Property]
    public string $id;

    #[Property]
    public string $name;
}
