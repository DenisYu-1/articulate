<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Entity;
use PHPUnit\Framework\TestCase;

class EntityAttributeTest extends TestCase {
    public function testEntityAttributeDefaultConstructor(): void
    {
        $entity = new Entity();

        $this->assertNull($entity->tableName);
    }

    public function testEntityAttributeWithTableName(): void
    {
        $entity = new Entity('custom_table');

        $this->assertEquals('custom_table', $entity->tableName);
    }

    public function testEntityAttributeWithNullTableName(): void
    {
        $entity = new Entity(null);

        $this->assertNull($entity->tableName);
    }

    public function testEntityAttributeWithEmptyStringTableName(): void
    {
        $entity = new Entity('');

        $this->assertEquals('', $entity->tableName);
    }

    public function testEntityAttributePropertiesArePublic(): void
    {
        $entity = new Entity('test_table');

        // Test that properties can be read
        $this->assertEquals('test_table', $entity->tableName);

        // Test that properties can be modified (though this would be unusual in practice)
        $entity->tableName = 'modified_table';
        $this->assertEquals('modified_table', $entity->tableName);
    }
}
