<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Attributes\Relations\ManyToMany;

#[Entity(tableName: 'shared_mapping_owner')]
class TestManyToManySharedOwner
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(
        targetEntity: TestManyToManySharedTarget::class,
        referencedBy: 'ownersFirst',
        mappingTable: new MappingTable(
            name: 'shared_owner_target_map',
            properties: [
                new MappingTableProperty(name: 'extra_one', type: 'string', nullable: false),
                new MappingTableProperty(name: 'shared_field', type: 'string', nullable: false),
            ],
        ),
    )]
    public array $firstRelations;

    #[ManyToMany(
        targetEntity: TestManyToManySharedTarget::class,
        referencedBy: 'ownersSecond',
        mappingTable: new MappingTable(
            name: 'shared_owner_target_map',
            properties: [
                new MappingTableProperty(name: 'shared_field', type: 'string', nullable: true),
                new MappingTableProperty(name: 'extra_three', type: 'int', nullable: false),
            ],
        ),
    )]
    public array $secondRelations;
}

#[Entity(tableName: 'shared_mapping_target')]
class TestManyToManySharedTarget
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(ownedBy: 'firstRelations', targetEntity: TestManyToManySharedOwner::class)]
    public array $ownersFirst;

    #[ManyToMany(ownedBy: 'secondRelations', targetEntity: TestManyToManySharedOwner::class)]
    public array $ownersSecond;
}

