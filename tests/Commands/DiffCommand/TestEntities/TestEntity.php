<?php

namespace Articulate\Tests\Commands\DiffCommand\TestEntities;

use Articulate\Attributes\Entity;

#[Entity]
class TestEntity {
    public int $id;

    public string $name;
}
