<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity]
class TestManyToOneOwner
{
    #[Property]
    public int $id;

    #[ManyToOne(referencedBy: 'owners')]
    public TestManyToOneTarget $target;
}
