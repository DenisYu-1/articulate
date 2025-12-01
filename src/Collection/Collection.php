<?php

namespace Norm\Collection;

use Iterator;

class Collection {
    private $items = [];

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function toArray(): array
    {
        return $this->items;
    }
}
