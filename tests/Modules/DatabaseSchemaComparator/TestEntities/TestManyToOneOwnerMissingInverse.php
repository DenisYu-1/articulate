<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity]
class TestManyToOneOwnerMissingInverse
{
    #[Property]
    public int $id;

    #[ManyToOne(referencedBy: 'missingProperty')]
    public TestManyToOneTargetMissingInverse $target;
}

