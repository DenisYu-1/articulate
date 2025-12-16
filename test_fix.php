<?php

require_once 'vendor/autoload.php';

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;

#[Entity]
class TestEntity
{
    #[PrimaryKey]
    #[Property(name: 'custom_pk')]
    public int $id;

    #[Property]
    public string $name;
}

$entity = new ReflectionEntity(TestEntity::class);
$primaryKeys = $entity->getPrimaryKeyColumns();

echo "Primary key columns: " . implode(', ', $primaryKeys) . "\n";

if ($primaryKeys === ['custom_pk']) {
    echo "SUCCESS: Primary key column name is correctly resolved!\n";
} else {
    echo "FAILED: Expected ['custom_pk'], got ['" . implode("', '", $primaryKeys) . "']\n";
}
