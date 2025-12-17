<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;

#[Entity]
class TestBoolEntity
{
    #[Property]
    public int $id;

    #[Property]
    public string $name;

    #[Property]
    public bool $isActive;

    #[Property]
    public ?bool $isFeatured;
}

