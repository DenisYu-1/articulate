<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphToMany;

#[Entity]
class TestPolymorphicManyToManyVideo
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    #[Property(maxLength: 500)]
    public string $description;

    #[Property]
    public string $url;

    #[Property]
    public ?string $thumbnailUrl;

    #[Property]
    public int $duration; // in seconds

    #[Property]
    public int $viewsCount;

    #[Property]
    public int $likesCount;

    #[Property]
    public int $creatorId;

    #[Property(maxLength: 10)]
    public string $format;

    #[Property]
    public int $fileSize; // in bytes

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
