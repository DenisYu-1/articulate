<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;

#[Entity]
class TestDateTimeEntity
{
    #[Property]
    public int $id;

    #[Property]
    public string $title;

    // These should all map to DATETIME columns
    #[Property]
    public \DateTime $createdAt;

    #[Property]
    public \DateTimeImmutable $updatedAt;

    #[Property]
    public ?\DateTimeInterface $publishedAt;
}
