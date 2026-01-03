<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphToMany;

#[Entity]
class TestPolymorphicManyToManyPost {
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    #[Property(maxLength: 255)]
    public string $slug;

    #[Property]
    public string $content;

    #[Property(maxLength: 500)]
    public ?string $excerpt;

    #[Property]
    public int $authorId;

    #[Property]
    public string $status;

    #[Property]
    public ?\DateTime $publishedAt;

    #[Property]
    public \DateTime $createdAt;

    #[Property]
    public \DateTime $updatedAt;

    #[MorphToMany(targetEntity: TestPolymorphicManyToManyTag::class, name: 'taggable')]
    public array $tags;
}
