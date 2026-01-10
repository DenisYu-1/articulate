<?php

namespace Test\Entities;

use Articulate\Attributes\Entity;

#[Entity]
class TestEntity {
    public int $id;
    public string $name;
}