<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity]
class TestManyToOneOwnerCustomColumn
{
    #[Property]
    public int $id;

    #[ManyToOne(column: 'custom_column_id', referencedBy: 'customOwners', nullable: true)]
    public TestManyToOneTarget $customTarget;
}

