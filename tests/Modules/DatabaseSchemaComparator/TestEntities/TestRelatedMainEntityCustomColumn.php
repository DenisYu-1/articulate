<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity]
class TestRelatedMainEntityCustomColumn
{
    #[Property]
    public int $id;

    #[OneToOne(inversedBy: 'name', column: 'custom_fk', mainSide: true)]
    public TestRelatedEntity $name;
}

