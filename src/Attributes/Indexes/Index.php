<?php

namespace Articulate\Attributes\Indexes;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Index {
    public array $columns = [];

    public function __construct(
        public readonly array $fields,
        public readonly bool $unique = false,
        public readonly ?string $name = null,
        public readonly bool $concurrent = false
    ) {
    }

    public function resolveColumns(ReflectionEntity $entity): array
    {
        $this->columns = [];

        foreach ($this->fields as $propertyName) {
            foreach ($entity->getEntityProperties() as $property) {
                if ($property instanceof ReflectionProperty && $property->getFieldName() === $propertyName) {
                    $this->columns[] = $property->getColumnName();

                    continue 2;
                }

                if ($property instanceof ReflectionRelation && $property->getPropertyName() === $propertyName) {
                    $this->columns[] = $property->getColumnName();

                    continue 2;
                }
            }

            throw new InvalidArgumentException(sprintf(
                'Index references unmapped property "%s" on entity "%s".',
                $propertyName,
                $entity->getName(),
            ));
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
