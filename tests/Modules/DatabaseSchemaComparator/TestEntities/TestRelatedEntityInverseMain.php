<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity(tableName: 'test_related_entity_inverse_main')]
class TestRelatedEntityInverseMain
{
    #[Property]
    public int $id;

    #[OneToOne] // misconfigured inverse: missing ownedBy
    public TestRelatedMainEntity $name;
}

