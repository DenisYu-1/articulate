<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphMany;

#[Entity]
class TestPostEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property(maxLength: 255)]
    public string $title;

    #[Property]
    public string $content;

    #[MorphMany(targetEntity: TestMorphToEntity::class, typeColumn: 'pollable_type', idColumn: 'pollable_id', referencedBy: 'pollable')]
    public array $morphToEntities;
}
