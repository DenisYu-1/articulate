<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToOne;

#[Entity(tableName: 'test_related_entity_inverse_fk')]
class TestRelatedEntityInverseForeignKey
{
    #[Property]
    public int $id;

    #[OneToOne(ownedBy: 'name', foreignKey: true)]
    public TestRelatedMainEntity $name;
}
