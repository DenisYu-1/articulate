<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;

#[Entity(tableName: 'shared_mapping_owner_conflict')]
class TestManyToManySharedOwnerConflict
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(
        targetEntity: TestManyToManySharedTargetConflict::class,
        referencedBy: 'ownersFirst',
        mappingTable: new MappingTable(
            name: 'shared_owner_conflict_map',
            properties: [
                new MappingTableProperty(name: 'shared_field', type: 'string', nullable: false),
            ],
        ),
    )]
    public array $firstRelations;

    #[ManyToMany(
        targetEntity: TestManyToManySharedTargetConflict::class,
        referencedBy: 'ownersSecond',
        mappingTable: new MappingTable(
            name: 'shared_owner_conflict_map',
            properties: [
                new MappingTableProperty(name: 'shared_field', type: 'int', nullable: false),
            ],
        ),
    )]
    public array $secondRelations;
}

#[Entity(tableName: 'shared_mapping_target_conflict')]
class TestManyToManySharedTargetConflict
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(ownedBy: 'firstRelations', targetEntity: TestManyToManySharedOwnerConflict::class)]
    public array $ownersFirst;

    #[ManyToMany(ownedBy: 'secondRelations', targetEntity: TestManyToManySharedOwnerConflict::class)]
    public array $ownersSecond;
}
