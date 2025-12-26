<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;

#[Entity(tableName: 'shared_table_base')]
class TestSharedTableVariantA
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[Property]
    public int $sharedField;
}

#[Entity(tableName: 'shared_table_base')]
class TestSharedTableVariantB
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[Property]
    public ?int $sharedField;
}

#[Entity(tableName: 'shared_table_base')]
class TestSharedTableVariantConflict
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[Property]
    public string $sharedField;
}
