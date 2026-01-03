<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_entity312')]
class TestMultiSortedPrimaryKeyEntity {
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
