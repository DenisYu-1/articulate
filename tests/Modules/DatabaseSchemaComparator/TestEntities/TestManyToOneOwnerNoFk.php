<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity]
class TestManyToOneOwnerNoFk {
    #[Property]
    public int $id;

    #[ManyToOne(referencedBy: 'nullableOwners', nullable: true, foreignKey: false)]
    public ?TestManyToOneTarget $nullableTarget;
}
