<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestOneToManyWrongOwnerType
{
    #[Property]
    public int $id;

    #[OneToMany(ownedBy: 'target', targetEntity: TestOneToManyWrongOwner::class)]
    public TestOneToManyWrongOwner $items;
}

