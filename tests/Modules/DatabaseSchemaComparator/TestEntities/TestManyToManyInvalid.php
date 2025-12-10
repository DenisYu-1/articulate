<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;

#[Entity(tableName: 'test_many_to_many_invalid_owner')]
class TestManyToManyInvalidOwner
{
    #[PrimaryKey]
    #[Property]
    public int $id;

    #[ManyToMany(targetEntity: TestManyToManyInvalidTarget::class, inversedBy: 'owners')]
    public array $targets;
}

#[Entity(tableName: 'test_many_to_many_invalid_target')]
class TestManyToManyInvalidTarget
{
    #[PrimaryKey]
    #[Property]
    public int $id;
}
