<?php

namespace Articulate\Utils;

class StringUtils {
    public static function snakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
