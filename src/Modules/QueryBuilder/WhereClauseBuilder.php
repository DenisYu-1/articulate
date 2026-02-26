<?php

namespace Articulate\Modules\QueryBuilder;

use InvalidArgumentException;

class WhereClauseBuilder {
    private array $where = [];

    public function __construct(
        private readonly \Closure $createSubQueryBuilder,
        private readonly SqlCompiler $sqlCompiler
    ) {
    }

    public function where(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (is_callable($columnOrCallback)) {
            return $this->whereGroupCallback($columnOrCallback, 'AND');
        }

        $isRawSql = $value === null && $this->isRawSqlCondition($columnOrCallback);

        if ($isRawSql) {
            $params = $this->extractRawParams($columnOrCallback, $operatorOrValue);

            $this->where[] = [
                'operator' => 'AND',
                'condition' => $columnOrCallback,
                'params' => $params,
                'group' => null,
            ];

            return $this;
        }

        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $condition = $this->buildConditionFromOperator($columnOrCallback, $operator, $value);
        $this->where[] = [
            'operator' => 'AND',
            'condition' => $condition['condition'],
            'params' => $condition['params'],
            'group' => null,
        ];

        return $this;
    }

    public function orWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (is_callable($columnOrCallback)) {
            return $this->whereGroupCallback($columnOrCallback, 'OR');
        }

        $isRawSql = $value === null && $this->isRawSqlCondition($columnOrCallback);

        if ($isRawSql) {
            $params = $this->extractRawParams($columnOrCallback, $operatorOrValue);

            $this->where[] = [
                'operator' => 'OR',
                'condition' => $columnOrCallback,
                'params' => $params,
                'group' => null,
            ];

            return $this;
        }

        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $condition = $this->buildConditionFromOperator($columnOrCallback, $operator, $value);
        $this->where[] = [
            'operator' => 'OR',
            'condition' => $condition['condition'],
            'params' => $condition['params'],
            'group' => null,
        ];

        return $this;
    }

    public function whereIn(string $column, array|QueryBuilder $values): self
    {
        if ($values instanceof QueryBuilder) {
            $this->where[] = [
                'operator' => 'AND',
                'condition' => "{$column} IN ({$values->getSQL()})",
                'params' => $values->getParameters(),
                'group' => null,
            ];

            return $this;
        }

        if (empty($values)) {
            return $this->where('1 = 0');
        }

        return $this->where("{$column} IN (?)", $values);
    }

    public function whereNotIn(string $column, array|QueryBuilder $values): self
    {
        if ($values instanceof QueryBuilder) {
            $this->where[] = [
                'operator' => 'AND',
                'condition' => "{$column} NOT IN ({$values->getSQL()})",
                'params' => $values->getParameters(),
                'group' => null,
            ];

            return $this;
        }

        if (empty($values)) {
            return $this->where('1 = 1');
        }

        return $this->where("{$column} NOT IN (?)", $values);
    }

    public function whereNull(string $column): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} IS NULL",
            'params' => [],
            'group' => null,
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "{$column} IS NOT NULL",
            'params' => [],
            'group' => null,
        ];

        return $this;
    }

    public function whereExists(QueryBuilder $subquery): self
    {
        $this->where[] = [
            'operator' => 'AND',
            'condition' => "EXISTS ({$subquery->getSQL()})",
            'params' => $subquery->getParameters(),
            'group' => null,
        ];

        return $this;
    }

    public function whereNot(callable $callback): self
    {
        $groupBuilder = ($this->createSubQueryBuilder)();
        $callback($groupBuilder);

        $groupConditions = $groupBuilder->getWhereConditions();
        if (!empty($groupConditions)) {
            $groupClause = $this->sqlCompiler->buildWhereClause($groupConditions);
            $this->where[] = [
                'operator' => 'AND',
                'condition' => "NOT ({$groupClause})",
                'params' => $this->sqlCompiler->collectWhereParametersPublic($groupConditions),
                'group' => null,
            ];
        }

        return $this;
    }

    public function orWhereExists(QueryBuilder $subquery): self
    {
        $this->where[] = [
            'operator' => 'OR',
            'condition' => "EXISTS ({$subquery->getSQL()})",
            'params' => $subquery->getParameters(),
            'group' => null,
        ];

        return $this;
    }

    public function whereRaw(string $condition, mixed ...$params): self
    {
        $actualParams = count($params) === 1 && is_array($params[0]) ? $params[0] : $params;

        $this->where[] = [
            'operator' => 'AND',
            'condition' => $condition,
            'params' => $actualParams,
            'group' => null,
            'raw' => true,
        ];

        return $this;
    }

    public function addCondition(string $condition, array $params, string $operator = 'AND'): void
    {
        $this->where[] = [
            'operator' => $operator,
            'condition' => $condition,
            'params' => $params,
            'group' => null,
        ];
    }

    public function getConditions(): array
    {
        return $this->where;
    }

    public function reset(): void
    {
        $this->where = [];
    }

    private function whereGroupCallback(callable $callback, string $operator): self
    {
        $groupBuilder = ($this->createSubQueryBuilder)();
        $callback($groupBuilder);

        $groupConditions = $groupBuilder->getWhereConditions();
        if (!empty($groupConditions)) {
            $this->where[] = [
                'operator' => $operator,
                'condition' => null,
                'params' => [],
                'group' => $groupConditions,
            ];
        }

        return $this;
    }

    private function isRawSqlCondition(string $columnOrCallback): bool
    {
        return str_contains($columnOrCallback, '?') ||
            str_contains($columnOrCallback, ' = ') ||
            str_contains($columnOrCallback, ' > ') ||
            str_contains($columnOrCallback, ' < ') ||
            str_contains($columnOrCallback, '>=') ||
            str_contains($columnOrCallback, '<=') ||
            str_contains($columnOrCallback, '!=') ||
            str_contains($columnOrCallback, '<>') ||
            stripos($columnOrCallback, ' IS ') !== false ||
            stripos($columnOrCallback, ' IN ') !== false ||
            stripos($columnOrCallback, ' LIKE ') !== false ||
            stripos($columnOrCallback, ' BETWEEN ') !== false;
    }

    private function extractRawParams(string $columnOrCallback, mixed $operatorOrValue): array
    {
        if ($operatorOrValue === null) {
            return [];
        }

        if (is_array($operatorOrValue)) {
            if (stripos($columnOrCallback, 'BETWEEN') !== false && stripos($columnOrCallback, 'AND') !== false) {
                return $operatorOrValue;
            }

            return [$operatorOrValue];
        }

        return [$operatorOrValue];
    }

    private function buildConditionFromOperator(string $column, string $operator, mixed $value): array
    {
        $operator = strtolower(trim($operator));

        return match ($operator) {
            '=', 'eq' => ['condition' => "{$column} = ?", 'params' => [$value]],
            '!=', '<>', 'ne' => ['condition' => "{$column} != ?", 'params' => [$value]],
            '>', 'gt' => ['condition' => "{$column} > ?", 'params' => [$value]],
            '<', 'lt' => ['condition' => "{$column} < ?", 'params' => [$value]],
            '>=', 'gte' => ['condition' => "{$column} >= ?", 'params' => [$value]],
            '<=', 'lte' => ['condition' => "{$column} <= ?", 'params' => [$value]],
            'like' => ['condition' => "{$column} LIKE ?", 'params' => [$value]],
            'not like' => ['condition' => "{$column} NOT LIKE ?", 'params' => [$value]],
            'between' => $this->buildBetweenCondition($column, $value, false),
            'not between' => $this->buildBetweenCondition($column, $value, true),
            default => throw new InvalidArgumentException("Unsupported operator: {$operator}"),
        };
    }

    private function buildBetweenCondition(string $column, mixed $value, bool $not): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException('BETWEEN operator requires an array with exactly 2 values');
        }

        $operator = $not ? 'NOT BETWEEN' : 'BETWEEN';

        return [
            'condition' => "{$column} {$operator} ? AND ?",
            'params' => [$value[0], $value[1]],
        ];
    }
}
