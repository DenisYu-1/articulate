<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_entity31')]
class TestMultiPrimaryKeyEntity {
    #[PrimaryKey]
    public string $id;

    #[PrimaryKey]
    public string $name;
}
