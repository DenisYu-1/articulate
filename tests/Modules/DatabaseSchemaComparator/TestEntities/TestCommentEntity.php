<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphMany;

#[Entity]
class TestCommentEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property(maxLength: 500)]
    public string $content;

    #[Property]
    public int $postId;

    #[MorphMany(targetEntity: TestMorphToEntity::class, typeColumn: 'pollable_type', idColumn: 'pollable_id', referencedBy: 'pollable')]
    public array $morphToEntities;
}
