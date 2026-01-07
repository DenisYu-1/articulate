<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;

#[Entity]
class TestEntity {
    #[PrimaryKey]
    public int $id;
}
