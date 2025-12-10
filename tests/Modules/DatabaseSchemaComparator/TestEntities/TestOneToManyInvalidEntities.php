<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestOneToManyInverseMissingOwner
{
    #[Property]
    public int $id;

    #[OneToMany(ownedBy: 'missingOwner', targetEntity: TestManyToOneOwner::class)]
    public TestManyToOneOwner $items;
}

#[Entity]
class TestOneToManyWrongOwnerType
{
    #[Property]
    public int $id;

    #[OneToMany(ownedBy: 'target', targetEntity: TestOneToManyWrongOwner::class)]
    public TestOneToManyWrongOwner $items;
}

#[Entity]
class TestOneToManyWrongOwner
{
    #[Property]
    public int $id;

    #[Property]
    public int $target;
}

