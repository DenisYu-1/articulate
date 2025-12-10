<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;

#[Entity(tableName: 'test_many_to_many_owner')]
class TestManyToManyOwner
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(targetEntity: TestManyToManyTarget::class, inversedBy: 'owners', mappingTable: new MappingTable(
        name: 'owner_target_map',
        properties: [new MappingTableProperty('created_at', 'datetime', true)]
    ))]
    public array $targets;
}

#[Entity(tableName: 'test_many_to_many_target')]
class TestManyToManyTarget
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(mappedBy: 'targets', targetEntity: TestManyToManyOwner::class)]
    public array $owners;
}
