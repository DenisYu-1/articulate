<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Relations\OneToMany;

#[Entity(tableName: 'fk_int_parent')]
class FkIntParent {
    #[PrimaryKey(generator: 'auto_increment')]
    public int $id;

    /** @var FkIntChild[] */
    #[OneToMany(ownedBy: 'parent', targetEntity: FkIntChild::class)]
    public array $children;
}
