<?php

namespace Articulate\Modules\QueryBuilder;

enum CursorDirection: string {
    case NEXT = 'next';
    case PREV = 'prev';
}
