<?php

namespace Articulate\Attributes\Reflection;

use Articulate\Attributes\Property;
use ReflectionProperty as BaseReflectionProperty;

interface PropertyInterface
{
    public function getColumnName(): string;

    public function isNullable(): bool;

    public function getType(): string;

    public function getDefaultValue(): ?string;

    public function getLength(): ?int;
}
