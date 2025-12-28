<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\MorphToMany;

#[Entity]
class TestPolymorphicManyToManyComment {
    #[Property]
    public int $id;

    #[Property]
    public string $content;

    #[Property]
    public int $postId;

    #[Property]
    public ?int $parentCommentId;

    #[Property]
    public ?int $authorId;

    #[Property(maxLength: 100)]
    public ?string $authorName;

    #[Property(maxLength: 255)]
    public ?string $authorEmail;

    #[Property]
    public int $depth;

    #[Property]
    public bool $isApproved;

    #[Property]
    public int $likesCount;

    #[Property]
    public int $reportsCount;

    #[Property(maxLength: 45)]
    public ?string $authorIp;

    #[Property]
    public \DateTime $createdAt;

    #[Property]
    public \DateTime $updatedAt;

    #[MorphToMany(targetEntity: TestPolymorphicManyToManyTag::class, name: 'taggable')]
    public array $tags;
}
