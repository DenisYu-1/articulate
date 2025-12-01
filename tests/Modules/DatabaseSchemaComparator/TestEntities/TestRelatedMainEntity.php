<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Norm\Attributes\Entity;
use Norm\Attributes\Relations\OneToOne;

#[Entity]
class TestRelatedMainEntity
{
    #[OneToOne(inversedBy: 'test_main_entity', mainSide: true)]
    public TestRelatedEntity $name;
}
