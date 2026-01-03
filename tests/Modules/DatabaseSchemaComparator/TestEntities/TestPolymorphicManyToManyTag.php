<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphedByMany;

#[Entity]
class TestPolymorphicManyToManyTag {
    #[Property]
    public int $id;

    #[Property(maxLength: 100)]
    public string $name;

    #[Property(maxLength: 120)]
    public string $slug;

    #[Property(maxLength: 500)]
    public ?string $description;

    #[Property(maxLength: 7)]
    public string $color;

    #[Property]
    public int $usageCount;

    #[Property]
    public bool $isFeatured;

    #[Property]
    public ?int $parentTagId;

    #[Property]
    public int $createdBy;

    #[Property]
    public \DateTime $createdAt;

    #[Property]
    public \DateTime $updatedAt;

    #[MorphedByMany(targetEntity: TestPolymorphicManyToManyPost::class, name: 'taggable')]
    public array $posts;

    #[MorphedByMany(targetEntity: TestPolymorphicManyToManyVideo::class, name: 'taggable')]
    public array $videos;

    #[MorphedByMany(targetEntity: TestPolymorphicManyToManyComment::class, name: 'taggable')]
    public array $comments;
}
