<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Modules\QueryBuilder\PlaceholderExpander;
use PHPUnit\Framework\TestCase;

class PlaceholderExpanderTest extends TestCase {
    public function testExpandWithNoArrayParams(): void
    {
        [$sql, $params] = PlaceholderExpander::expand(
            'SELECT * FROM users WHERE id = ? AND name = ?',
            [1, 'John']
        );

        $this->assertSame('SELECT * FROM users WHERE id = ? AND name = ?', $sql);
        $this->assertSame([1, 'John'], $params);
    }

    public function testExpandWithSingleArray(): void
    {
        [$sql, $params] = PlaceholderExpander::expand(
            'SELECT * FROM users WHERE id IN (?)',
            [[1, 2, 3]]
        );

        $this->assertSame('SELECT * FROM users WHERE id IN (?,?,?)', $sql);
        $this->assertSame([1, 2, 3], $params);
    }

    public function testExpandWithMultipleArrays(): void
    {
        [$sql, $params] = PlaceholderExpander::expand(
            'SELECT * FROM users WHERE id IN (?) AND status IN (?)',
            [[1, 2, 3], ['active', 'pending']]
        );

        $this->assertSame('SELECT * FROM users WHERE id IN (?,?,?) AND status IN (?,?)', $sql);
        $this->assertSame([1, 2, 3, 'active', 'pending'], $params);
    }

    public function testExpandWithEmptyArray(): void
    {
        [$sql, $params] = PlaceholderExpander::expand(
            'SELECT * FROM users WHERE id IN (?)',
            [[]]
        );

        $this->assertSame('SELECT * FROM users WHERE id IN (?)', $sql);
        $this->assertSame([null], $params);
    }

    public function testExpandWithMixedParams(): void
    {
        [$sql, $params] = PlaceholderExpander::expand(
            'SELECT * FROM users WHERE name = ? AND id IN (?) AND active = ?',
            ['John', [1, 2, 3], true]
        );

        $this->assertSame('SELECT * FROM users WHERE name = ? AND id IN (?,?,?) AND active = ?', $sql);
        $this->assertSame(['John', 1, 2, 3, true], $params);
    }

    public function testExpandWithLargeArray(): void
    {
        $ids = range(1, 1000);

        [$sql, $params] = PlaceholderExpander::expand(
            'SELECT * FROM users WHERE id IN (?)',
            [$ids]
        );

        $expectedPlaceholders = implode(',', array_fill(0, 1000, '?'));
        $this->assertSame("SELECT * FROM users WHERE id IN ({$expectedPlaceholders})", $sql);
        $this->assertSame($ids, $params);
    }
}
