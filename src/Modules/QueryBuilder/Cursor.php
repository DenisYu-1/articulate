<?php

namespace Articulate\Modules\QueryBuilder;

class Cursor {
    public function __construct(
        private readonly array $values,
        private readonly CursorDirection $direction
    ) {
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getDirection(): CursorDirection
    {
        return $this->direction;
    }
}
