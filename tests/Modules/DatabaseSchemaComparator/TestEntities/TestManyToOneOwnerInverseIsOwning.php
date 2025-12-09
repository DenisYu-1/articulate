<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity]
class TestManyToOneOwnerInverseIsOwning
{
    #[Property]
    public int $id;

    #[ManyToOne(inversedBy: 'owners')]
    public TestManyToOneTargetInverseIsOwning $target;
}

