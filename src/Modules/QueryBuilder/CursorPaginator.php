<?php

namespace Articulate\Modules\QueryBuilder;

class CursorPaginator
{
    public function __construct(
        private readonly array $items,
        private readonly ?string $nextCursor = null,
        private readonly ?string $prevCursor = null
    ) {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function getPrevCursor(): ?string
    {
        return $this->prevCursor;
    }

    public function hasNext(): bool
    {
        return $this->nextCursor !== null;
    }

    public function hasPrev(): bool
    {
        return $this->prevCursor !== null;
    }
}
