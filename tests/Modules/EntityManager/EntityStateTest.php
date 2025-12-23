<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\EntityState;
use PHPUnit\Framework\TestCase;

class EntityStateTest extends TestCase
{
    public function testEntityStateEnumValues(): void
    {
        $this->assertEquals('NEW', EntityState::NEW->name);
        $this->assertEquals('MANAGED', EntityState::MANAGED->name);
        $this->assertEquals('REMOVED', EntityState::REMOVED->name);
    }

    public function testEntityStateEnumCases(): void
    {
        $cases = EntityState::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(EntityState::NEW, $cases);
        $this->assertContains(EntityState::MANAGED, $cases);
        $this->assertContains(EntityState::REMOVED, $cases);
    }

    public function testEntityStateValuesAreUnique(): void
    {
        $values = array_map(fn(EntityState $state) => $state->name, EntityState::cases());
        $this->assertCount(3, array_unique($values));
    }
}
