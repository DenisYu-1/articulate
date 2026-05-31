<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Relations\ManyToOne;

#[Entity(tableName: 'fk_int_child')]
class FkIntChild {
    #[PrimaryKey(generator: 'auto_increment')]
    public int $id;

    #[ManyToOne(referencedBy: 'children')]
    public FkIntParent $parent;
}
