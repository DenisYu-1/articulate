<?php

namespace Articulate\Utils;

/**
 * Example Point class for spatial data.
 */
class Point
{
    public function __construct(
        public readonly float $x,
        public readonly float $y
    ) {
    }

    public function toString(): string
    {
        return sprintf('POINT(%f %f)', $this->x, $this->y);
    }

    public static function fromString(string $pointString): self
    {
        // Parse POINT(x y) format
        if (preg_match('/POINT\(([^ ]+) ([^)]+)\)/', $pointString, $matches)) {
            return new self((float) $matches[1], (float) $matches[2]);
        }

        throw new \InvalidArgumentException("Invalid POINT format: {$pointString}");
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
