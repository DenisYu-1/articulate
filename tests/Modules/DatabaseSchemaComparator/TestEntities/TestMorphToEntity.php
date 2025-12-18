<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphTo;

#[Entity]
class TestMorphToEntity
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    #[MorphTo]
    public $pollable;
}
