<?php

namespace Norm\Attributes\Indexes;

use Attribute;
use Norm\Attributes\Property;
use Norm\Attributes\Reflection\ReflectionEntity;
use Norm\Attributes\Reflection\ReflectionProperty;
use ReflectionClass;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Index
{
    public array $columns = [];
    public function __construct(
        public readonly array $fields,
        public readonly bool $unique = false,
        public readonly ?string $name = null
    ) {}

    public function resolveColumns(ReflectionEntity $entity): array
    {
        // Iterate over entity properties to find column names
        foreach ($this->fields as $propertyName) {
            $property = $entity->getProperty($propertyName);

            $attributes = $property->getAttributes(Property::class);
            if (!empty($attributes)) {
                $reflectionProperty = new ReflectionProperty($attributes[0]->newInstance(), $property);
                $this->columns[] = $reflectionProperty->getColumnName();
            }
        }
        return $this->columns;
    }

    public function getName(): string
    {
        if ($this->name) {
            return $this->name;
        }
        $indexName = implode('_', $this->columns) . '_idx';

        // Optionally, ensure the index name doesn't exceed a certain length (MySQL limits to 64 characters)
        if (strlen($indexName) > 64) {
            // Shorten the index name if needed, typically by using a hash
            $indexName = md5($indexName);
        }
        return $indexName;
    }
}
