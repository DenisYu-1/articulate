<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Property;
use Norm\Attributes\Relations\OneToOne;

#[Entity(tableName: 'test_entity')]
class TestRelatedEntity
{
    #[Property]
    public int $id;

    #[OneToOne(mappedBy: 'testRelatedEntity')]
    public TestRelatedMainEntity $name;
}
