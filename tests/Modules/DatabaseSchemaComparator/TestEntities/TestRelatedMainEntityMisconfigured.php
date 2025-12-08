<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity]
class TestRelatedMainEntityMisconfigured
{
    #[Property]
    public int $id;

    #[OneToOne(inversedBy: 'name', mainSide: true)]
    public TestRelatedEntityMisconfigured $name;
}

