<?php

namespace Articulate\Modules\QueryBuilder;

class PlaceholderExpander {
    /**
     * Expand IN (?) placeholders for array parameters.
     * Converts "col IN (?)" with [[1,2,3]] to "col IN (?,?,?)" with [1,2,3].
     *
     * @return array{string, array}
     */
    public static function expand(string $sql, array $params): array
    {
        $expandedParams = [];
        $paramIndex = 0;

        $newSql = preg_replace_callback('/\?/', function () use ($params, &$paramIndex, &$expandedParams) {
            if (!isset($params[$paramIndex])) {
                return '?';
            }

            $param = $params[$paramIndex];
            $paramIndex++;

            if (is_array($param)) {
                $count = count($param);
                if ($count === 0) {
                    $expandedParams[] = null;

                    return '?';
                }

                foreach ($param as $value) {
                    $expandedParams[] = $value;
                }

                return implode(',', array_fill(0, $count, '?'));
            }

            $expandedParams[] = $param;

            return '?';
        }, $sql);

        return [$newSql, $expandedParams];
    }
}
