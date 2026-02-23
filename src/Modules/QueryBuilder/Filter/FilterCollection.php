<?php

namespace Articulate\Modules\QueryBuilder\Filter;

use Articulate\Modules\EntityManager\EntityMetadata;

class FilterCollection
{
    /** @var array<string, QueryFilterInterface> */
    private array $filters = [];

    /** @var array<string, bool> */
    private array $enabled = [];

    public function add(string $name, QueryFilterInterface $filter, bool $enabledByDefault = true): void
    {
        $this->filters[$name] = $filter;
        $this->enabled[$name] = $enabledByDefault;
    }

    public function enable(string $name): void
    {
        if (isset($this->filters[$name])) {
            $this->enabled[$name] = true;
        }
    }

    public function disable(string $name): void
    {
        if (isset($this->filters[$name])) {
            $this->enabled[$name] = false;
        }
    }

    public function isEnabled(string $name): bool
    {
        return $this->enabled[$name] ?? false;
    }

    /** @return array<string, string> filter name => SQL fragment */
    public function getActiveConditions(EntityMetadata $metadata): array
    {
        $conditions = [];

        foreach ($this->filters as $name => $filter) {
            if (!($this->enabled[$name] ?? true)) {
                continue;
            }

            $condition = $filter->getCondition($metadata);
            if ($condition !== null) {
                $conditions[$name] = $condition;
            }
        }

        return $conditions;
    }
}
