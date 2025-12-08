<?php

namespace Articulate\Attributes\Indexes;

use Attribute;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use ReflectionAttribute;
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

            /** @var ReflectionAttribute<Property>[] $attributes */
            $attributes = $property->getAttributes(Property::class);
            if (!empty($attributes)) {
                /** @var Property $entityProperty */
                $entityProperty = $attributes[0]->newInstance();
                $reflectionProperty = new ReflectionProperty($entityProperty, $property);
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
