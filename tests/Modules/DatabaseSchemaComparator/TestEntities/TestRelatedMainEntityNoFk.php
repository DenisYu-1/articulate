<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Relations\OneToOne;

#[Entity]
class TestRelatedMainEntityNoFk
{
    #[OneToOne(referencedBy: 'test_main_entity_no_fk', foreignKey: false)]
    public TestRelatedEntity $name;
}

