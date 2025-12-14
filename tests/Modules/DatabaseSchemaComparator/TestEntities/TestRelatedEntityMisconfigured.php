<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity(tableName: 'test_related_entity_misconfigured')]
class TestRelatedEntityMisconfigured
{
    #[Property]
    public int $id;

    #[OneToOne(ownedBy: 'wrong_property')]
    public TestRelatedMainEntity $name;
}
