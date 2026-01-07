<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;

#[Entity(tableName: 'test_entity_missing')]
class TestEntityMissing {
    #[PrimaryKey]
    public int $id;
}
