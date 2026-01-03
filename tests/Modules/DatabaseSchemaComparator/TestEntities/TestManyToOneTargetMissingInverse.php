<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;

#[Entity]
class TestManyToOneTargetMissingInverse {
    #[Property]
    public int $id;
}
