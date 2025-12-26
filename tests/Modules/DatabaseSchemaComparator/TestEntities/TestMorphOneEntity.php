<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphOne;

#[Entity]
class TestMorphOneEntity
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $name;

    #[MorphOne(targetEntity: TestMorphToEntity::class, referencedBy: 'pollable')]
    public $morphToEntity;
}
