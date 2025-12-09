<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestManyToOneTarget
{
    #[Property]
    public int $id;

    #[OneToMany(mappedBy: 'target', targetEntity: TestManyToOneOwner::class)]
    public TestManyToOneOwner $owners;

    #[OneToMany(mappedBy: 'nullableTarget', targetEntity: TestManyToOneOwnerNoFk::class)]
    public TestManyToOneOwnerNoFk $nullableOwners;

    #[OneToMany(mappedBy: 'customTarget', targetEntity: TestManyToOneOwnerCustomColumn::class)]
    public TestManyToOneOwnerCustomColumn $customOwners;
}

