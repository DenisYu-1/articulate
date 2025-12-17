<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity(tableName: 'shared_table_rel')]
class TestSharedTableRelationOwnerA
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToOne(targetEntity: TestSharedTableRelationTarget::class)]
    public TestSharedTableRelationTarget $target;
}

#[Entity(tableName: 'shared_table_rel')]
class TestSharedTableRelationOwnerB
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToOne(targetEntity: TestSharedTableRelationTarget::class, nullable: true)]
    public ?TestSharedTableRelationTarget $target;
}

#[Entity(tableName: 'shared_table_relation_target')]
class TestSharedTableRelationTarget
{
    #[PrimaryKey]
    #[Property]
    public int $id;
}

