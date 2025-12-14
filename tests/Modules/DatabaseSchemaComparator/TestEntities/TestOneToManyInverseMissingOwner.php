<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestOneToManyInverseMissingOwner
{
    #[Property]
    public int $id;

    #[OneToMany(ownedBy: 'missingOwner', targetEntity: TestManyToOneOwner::class)]
    public TestManyToOneOwner $items;
}
