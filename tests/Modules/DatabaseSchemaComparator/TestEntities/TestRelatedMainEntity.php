<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity]
class TestRelatedMainEntity
{
    #[Property]
    public int $id;

    #[OneToOne(referencedBy: 'name')]
    public TestRelatedEntity $name;
}
