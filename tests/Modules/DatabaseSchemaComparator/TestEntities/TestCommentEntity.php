<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphMany;

#[Entity]
class TestCommentEntity {
    #[Property]
    public int $id;

    #[Property(maxLength: 500)]
    public string $content;

    #[Property]
    public int $postId;

    #[MorphMany(targetEntity: TestMorphToEntity::class, referencedBy: 'pollable')]
    public array $morphToEntities;
}
