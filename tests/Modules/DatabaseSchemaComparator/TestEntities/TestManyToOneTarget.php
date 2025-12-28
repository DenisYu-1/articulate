<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestManyToOneTarget {
    #[Property]
    public int $id;

    #[OneToMany(ownedBy: 'target', targetEntity: TestManyToOneOwner::class)]
    public array $owners;

    #[OneToMany(ownedBy: 'nullableTarget', targetEntity: TestManyToOneOwnerNoFk::class)]
    public array $nullableOwners;

    #[OneToMany(ownedBy: 'customTarget', targetEntity: TestManyToOneOwnerCustomColumn::class)]
    public array $customOwners;
}
