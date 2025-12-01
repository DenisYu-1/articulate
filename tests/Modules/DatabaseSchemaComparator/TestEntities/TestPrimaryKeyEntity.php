<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Indexes\PrimaryKey;
use Norm\Attributes\Property;

#[Entity(tableName: 'test_entity3')]
class TestPrimaryKeyEntity
{
    #[PrimaryKey]
    #[Property]
    public string $id;
    #[Property]
    public string $name;
}
