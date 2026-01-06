<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_entity312')]
class TestMultiSortedPrimaryKeyEntity {
    #[PrimaryKey]
    public string $id;

    #[PrimaryKey]
    public string $name;

    #[PrimaryKey]
    public string $abc;
}
