<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Indexes\PrimaryKey;
use Norm\Attributes\Property;

#[Entity(tableName: 'test_entity312')]
class TestMultiSortedPrimaryKeyEntity
{
    #[PrimaryKey]
    #[Property]
    public string $id;

    #[PrimaryKey]
    #[Property]
    public string $name;

    #[PrimaryKey]
    #[Property]
    public string $abc;
}
