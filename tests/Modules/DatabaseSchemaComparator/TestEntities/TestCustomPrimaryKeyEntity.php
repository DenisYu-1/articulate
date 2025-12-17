<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'test_custom_pk_entity')]
class TestCustomPrimaryKeyEntity
{
    #[PrimaryKey]
    #[Property(name: 'custom_id')]
    public string $id;

    #[Property]
    public string $name;
}
