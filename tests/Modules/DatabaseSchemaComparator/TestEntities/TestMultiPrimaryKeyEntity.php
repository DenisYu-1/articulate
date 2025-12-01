<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Indexes\PrimaryKey;
use Norm\Attributes\Property;

#[Entity(tableName: 'test_entity31')]
class TestMultiPrimaryKeyEntity
{
    #[PrimaryKey]
    public string $id;
    #[PrimaryKey]
    #[Property]
    public string $name;
}
