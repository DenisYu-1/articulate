<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The structural statement-cache key must include every clause that changes the compiled
 * SQL, otherwise a cache hit hands back SQL built for a different query shape.
 */
class SqlStatementCacheKeyTest extends TestCase {
    private ArrayCache $pool;

    protected function setUp(): void
    {
        $this->pool = new ArrayCache();
    }

    public function testIdenticalShapesShareCachedSqlButKeepOwnParameters(): void
    {
        $first = $this->qb()->select('id')->from('users')->where('id', 1);
        $second = $this->qb()->select('id')->from('users')->where('id', 2);

        $this->assertSame($first->getSQL(), $second->getSQL());
        $this->assertSame([2], $second->getParameters());
    }

    /**
     * @param callable(QueryBuilder): QueryBuilder $left
     * @param callable(QueryBuilder): QueryBuilder $right
     */
    #[DataProvider('structuralVariants')]
    public function testStructuralDifferenceProducesDifferentSql(callable $left, callable $right): void
    {
        $leftSql = $left($this->qb())->getSQL();
        $rightSql = $right($this->qb())->getSQL();

        $this->assertNotSame($leftSql, $rightSql);
    }

    public static function structuralVariants(): array
    {
        $base = static fn (QueryBuilder $qb): QueryBuilder => $qb->select('id')->from('users')->where('id', 1);
        $grouped = static fn (QueryBuilder $qb): QueryBuilder => $base($qb)->groupBy('name');
        $ordered = static fn (QueryBuilder $qb): QueryBuilder => $base($qb)->orderBy('id', 'ASC');
        $limited = static fn (QueryBuilder $qb): QueryBuilder => $base($qb)->limit(5);

        return [
            'select' => [$base, static fn (QueryBuilder $qb) => $qb->select('name')->from('users')->where('id', 1)],
            'from' => [$base, static fn (QueryBuilder $qb) => $qb->select('id')->from('accounts')->where('id', 1)],
            'join' => [$base, static fn (QueryBuilder $qb) => $base($qb)->join('roles', 'roles.id = users.role_id')],
            'where' => [$base, static fn (QueryBuilder $qb) => $qb->select('id')->from('users')->where('name', 'ann')],
            'groupBy' => [$base, $grouped],
            'having' => [$grouped, static fn (QueryBuilder $qb) => $grouped($qb)->having('COUNT(*) > 1')],
            'orderBy direction' => [$ordered, static fn (QueryBuilder $qb) => $base($qb)->orderBy('id', 'DESC')],
            'limit' => [$base, $limited],
            'offset' => [$limited, static fn (QueryBuilder $qb) => $limited($qb)->offset(10)],
            'distinct' => [$base, static fn (QueryBuilder $qb) => $base($qb)->distinct()],
            'lockForUpdate' => [$base, static fn (QueryBuilder $qb) => $base($qb)->lock()],
            'cursorLimit vs limit' => [
                static fn (QueryBuilder $qb) => $ordered($qb)->limit(3),
                static fn (QueryBuilder $qb) => $ordered($qb)->cursorLimit(3),
            ],
        ];
    }

    private function qb(): QueryBuilder
    {
        return new QueryBuilder(
            $this->createStub(Connection::class),
            null,
            null,
            null,
            null,
            $this->pool
        );
    }
}
