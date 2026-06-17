<?php

namespace Articulate\Utils;

final class ReflectionCache {
    /** @var array<string, \ReflectionClass<object>> */
    private static array $classes = [];

    /** @var array<string, \ReflectionProperty> */
    private static array $properties = [];

    /** @return \ReflectionClass<object> */
    public static function getClass(string $class): \ReflectionClass
    {
        return self::$classes[$class] ??= new \ReflectionClass($class);
    }

    public static function getProperty(string $class, string $property): \ReflectionProperty
    {
        $key = $class . '::' . $property;

        return self::$properties[$key] ??= new \ReflectionProperty($class, $property);
    }
}
