<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Exceptions\CursorPaginationException;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;

class CursorPaginationHandler
{
    public function __construct(
        private readonly CursorCodec $cursorCodec,
        private readonly ?EntityMetadataRegistry $metadataRegistry
    ) {
    }

    public function validateCursorPagination(array $orderBy, ?int $cursorLimit): void
    {
        if (empty($orderBy)) {
            throw new CursorPaginationException('ORDER BY clause is required for cursor pagination');
        }

        if (count($orderBy) > 2) {
            throw new CursorPaginationException('Cursor pagination supports maximum 2 ORDER BY columns');
        }

        if ($cursorLimit === null) {
            throw new CursorPaginationException('cursorLimit() must be called before getCursorPaginatedResult()');
        }
    }

    public function parseOrderByForCursor(array $orderBy): array
    {
        $parsed = [];
        foreach ($orderBy as $orderItem) {
            if (preg_match('/^(.+?)\s+(ASC|DESC)$/i', trim($orderItem), $matches)) {
                $parsed[] = [
                    'column' => trim($matches[1]),
                    'direction' => strtoupper($matches[2]),
                ];
            } else {
                $parsed[] = [
                    'column' => trim($orderItem),
                    'direction' => 'ASC',
                ];
            }
        }
        return $parsed;
    }

    public function extractCursorValues(mixed $item, array $parsedOrderBy, ?string $entityClass): ?array
    {
        $values = [];

        foreach ($parsedOrderBy as $order) {
            $column = $order['column'];

            if (is_object($item)) {
                if ($entityClass !== null && $this->metadataRegistry !== null) {
                    try {
                        $metadata = $this->metadataRegistry->getMetadata($entityClass);
                        foreach ($metadata->getProperties() as $property) {
                            if ($property->getColumnName() === $column && $property instanceof ReflectionProperty) {
                                $values[] = $property->getValue($item);
                                continue 2;
                            }
                        }
                    } catch (\Exception $e) {
                    }
                }

                try {
                    $reflectionEntity = new ReflectionEntity($item::class);
                    foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                        if ($property instanceof ReflectionProperty && ($property->getColumnName() === $column || $property->getFieldName() === $column)) {
                            $values[] = $property->getValue($item);
                            continue 2;
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            if (is_array($item) && isset($item[$column])) {
                $values[] = $item[$column];
                continue;
            }

            return null;
        }

        return $values;
    }

    public function createPaginator(
        array $items,
        array $orderBy,
        ?Cursor $currentCursor,
        int $cursorLimit
    ): CursorPaginator {
        $parsedOrderBy = $this->parseOrderByForCursor($orderBy);
        $nextCursor = null;
        $prevCursor = null;

        if (!empty($items)) {
            $lastItem = end($items);
            $lastValues = $this->extractCursorValues($lastItem, $parsedOrderBy, null);
            if ($lastValues !== null) {
                $nextCursor = $this->cursorCodec->encode(new Cursor($lastValues, CursorDirection::NEXT));
            }

            $firstItem = reset($items);
            $firstValues = $this->extractCursorValues($firstItem, $parsedOrderBy, null);
            if ($firstValues !== null) {
                $prevCursor = $this->cursorCodec->encode(new Cursor($firstValues, CursorDirection::PREV));
            }
        }

        $hasMore = count($items) === $cursorLimit;
        if (!$hasMore) {
            $nextCursor = null;
        }

        if ($currentCursor === null || $currentCursor->getDirection() === CursorDirection::NEXT) {
            return new CursorPaginator($items, $nextCursor, $prevCursor);
        }

        return new CursorPaginator(array_reverse($items), $prevCursor, $nextCursor);
    }

    public function createPaginatorWithEntityClass(
        array $items,
        array $orderBy,
        ?Cursor $currentCursor,
        int $cursorLimit,
        ?string $entityClass
    ): CursorPaginator {
        $parsedOrderBy = $this->parseOrderByForCursor($orderBy);
        $nextCursor = null;
        $prevCursor = null;

        if (!empty($items)) {
            $lastItem = end($items);
            $lastValues = $this->extractCursorValues($lastItem, $parsedOrderBy, $entityClass);
            if ($lastValues !== null) {
                $nextCursor = $this->cursorCodec->encode(new Cursor($lastValues, CursorDirection::NEXT));
            }

            $firstItem = reset($items);
            $firstValues = $this->extractCursorValues($firstItem, $parsedOrderBy, $entityClass);
            if ($firstValues !== null) {
                $prevCursor = $this->cursorCodec->encode(new Cursor($firstValues, CursorDirection::PREV));
            }
        }

        $hasMore = count($items) === $cursorLimit;
        if (!$hasMore) {
            $nextCursor = null;
        }

        if ($currentCursor === null || $currentCursor->getDirection() === CursorDirection::NEXT) {
            return new CursorPaginator($items, $nextCursor, $prevCursor);
        }

        return new CursorPaginator(array_reverse($items), $prevCursor, $nextCursor);
    }
}
