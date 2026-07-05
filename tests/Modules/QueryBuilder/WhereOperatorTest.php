<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhereOperatorTest extends TestCase {
    private QueryBuilder $qb;

    protected function setUp(): void
    {
        $this->qb = new QueryBuilder($this->createStub(Connection::class));
    }

    /**
     * @return iterable<string, array{string, mixed, string, array<int, mixed>}>
     */
    public static function fluentOperatorProvider(): iterable
    {
        yield 'equals alias' => ['eq', 10, 'age = ?', [10]];
        yield 'not equals bang' => ['!=', 'archived', 'age != ?', ['archived']];
        yield 'not equals angle' => ['<>', 'archived', 'age != ?', ['archived']];
        yield 'not equals alias' => ['ne', 'archived', 'age != ?', ['archived']];
        yield 'greater than symbol' => ['>', 18, 'age > ?', [18]];
        yield 'greater than alias' => ['gt', 18, 'age > ?', [18]];
        yield 'less than symbol' => ['<', 65, 'age < ?', [65]];
        yield 'less than alias' => ['lt', 65, 'age < ?', [65]];
        yield 'greater than or equal symbol' => ['>=', 18, 'age >= ?', [18]];
        yield 'greater than or equal alias' => ['gte', 18, 'age >= ?', [18]];
        yield 'less than or equal symbol' => ['<=', 65, 'age <= ?', [65]];
        yield 'less than or equal alias' => ['lte', 65, 'age <= ?', [65]];
        yield 'like' => ['like', 'a%', 'age LIKE ?', ['a%']];
        yield 'not like' => ['not like', 'a%', 'age NOT LIKE ?', ['a%']];
        yield 'between' => ['between', [18, 65], 'age BETWEEN ? AND ?', [18, 65]];
        yield 'not between' => ['not between', [18, 65], 'age NOT BETWEEN ? AND ?', [18, 65]];
        yield 'trimmed uppercase operator' => [' GTE ', 21, 'age >= ?', [21]];
    }

    #[DataProvider('fluentOperatorProvider')]
    public function testFluentWhereOperators(string $operator, mixed $value, string $expectedCondition, array $expectedParams): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('age', $operator, $value);

        $this->assertSame("SELECT * FROM users WHERE {$expectedCondition}", $qb->getSQL());
        $this->assertSame($expectedParams, $qb->getParameters());
    }

    /**
     * @return iterable<string, array{string, mixed, array<int, mixed>}>
     */
    public static function rawConditionProvider(): iterable
    {
        yield 'placeholder only' => ['score ?', 10, [10]];
        yield 'equals with spaces' => ['score = ?', 10, [10]];
        yield 'greater than with spaces' => ['score > ?', 10, [10]];
        yield 'less than with spaces' => ['score < ?', 10, [10]];
        yield 'greater than or equal' => ['score >= ?', 10, [10]];
        yield 'less than or equal' => ['score <= ?', 10, [10]];
        yield 'not equals bang' => ['score != ?', 10, [10]];
        yield 'not equals angle' => ['score <> ?', 10, [10]];
        yield 'is expression' => ['deleted_at IS NULL', null, []];
        yield 'in expression' => ['role IN (?)', ['admin', 'editor'], [['admin', 'editor']]];
        yield 'like expression' => ['name LIKE ?', 'a%', ['a%']];
        yield 'between expression' => ['score BETWEEN ? AND ?', [10, 20], [10, 20]];
    }

    #[DataProvider('rawConditionProvider')]
    public function testRawConditionDetectionKeepsConditionVerbatim(string $condition, mixed $value, array $expectedParams): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where($condition, $value);

        $this->assertSame("SELECT * FROM users WHERE {$condition}", $qb->getSQL());
        $this->assertSame($expectedParams, $qb->getParameters());
    }

    public function testWhereNullValueProducesIsNull(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('deleted_at', null);

        $this->assertSame('SELECT * FROM users WHERE deleted_at IS NULL', $qb->getSQL());
        $this->assertSame([], $qb->getParameters());
    }

    public function testOrWhereNullValueProducesIsNull(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('status', 'active')
            ->orWhere('deleted_at', null);

        $this->assertSame('SELECT * FROM users WHERE status = ? OR deleted_at IS NULL', $qb->getSQL());
        $this->assertSame(['active'], $qb->getParameters());
    }

    public function testInvalidOperatorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator: contains');

        $this->qb
            ->select('*')
            ->from('users')
            ->where('name', 'contains', 'adm')
            ->getSQL();
    }

    public function testBetweenRequiresExactlyTwoValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BETWEEN operator requires an array with exactly 2 values');

        $this->qb
            ->select('*')
            ->from('users')
            ->where('age', 'between', [18])
            ->getSQL();
    }
}
