<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphMany;

#[Entity]
class TestPostEntity
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    #[Property]
    public string $content;

    #[MorphMany(targetEntity: TestMorphToEntity::class, referencedBy: 'pollable')]
    public array $morphToEntities;
}
