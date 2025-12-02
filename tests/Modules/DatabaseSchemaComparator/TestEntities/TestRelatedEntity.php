<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity(tableName: 'test_entity')]
class TestRelatedEntity
{
    #[Property]
    public int $id;

    #[OneToOne(mappedBy: 'testRelatedEntity')]
    public TestRelatedMainEntity $name;
}
