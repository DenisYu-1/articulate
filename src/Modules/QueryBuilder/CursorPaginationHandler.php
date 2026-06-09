<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Exceptions\CursorPaginationException;
use Articulate\Schema\EntityMetadataRegistry;

class CursorPaginationHandler {
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
                            if ($property->getColumnName() === $column) {
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
        int $cursorLimit,
        ?string $entityClass = null
    ): CursorPaginator {
        $parsedOrderBy = $this->parseOrderByForCursor($orderBy);
        $nextCursor = null;
        $prevCursor = null;

        $hasMore = count($items) > $cursorLimit;
        if ($hasMore) {
            $items = array_slice($items, 0, $cursorLimit);
        }

        if (!empty($items)) {
            $isPrev = $currentCursor?->getDirection() === CursorDirection::PREV;

            if ($isPrev) {
                // DB returned items in reversed order: first = highest value, last = lowest value
                $nextBoundaryItem = reset($items);
                $prevBoundaryItem = end($items);
            } else {
                $nextBoundaryItem = end($items);
                $prevBoundaryItem = reset($items);
            }

            $nextValues = $this->extractCursorValues($nextBoundaryItem, $parsedOrderBy, $entityClass);
            if ($nextValues !== null) {
                $nextCursor = $this->cursorCodec->encode(new Cursor($nextValues, CursorDirection::NEXT));
            }

            if ($currentCursor !== null) {
                $prevValues = $this->extractCursorValues($prevBoundaryItem, $parsedOrderBy, $entityClass);
                if ($prevValues !== null) {
                    $prevCursor = $this->cursorCodec->encode(new Cursor($prevValues, CursorDirection::PREV));
                }
            }
        }

        if (!$hasMore) {
            if ($currentCursor?->getDirection() === CursorDirection::PREV) {
                $prevCursor = null;
            } else {
                $nextCursor = null;
            }
        }

        if ($currentCursor?->getDirection() === CursorDirection::PREV) {
            return new CursorPaginator(array_reverse($items), $nextCursor, $prevCursor);
        }

        return new CursorPaginator($items, $nextCursor, $prevCursor);
    }
}
