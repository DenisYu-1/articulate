<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Relations\OneToOne;

#[Entity]
class TestRelatedMainEntity
{
    #[OneToOne(inversedBy: 'test_main_entity', mainSide: true)]
    public TestRelatedEntity $name;
}
