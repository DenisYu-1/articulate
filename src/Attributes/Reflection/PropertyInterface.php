<?php

namespace Articulate\Attributes\Reflection;

interface PropertyInterface {
    public function getColumnName(): string;

    public function isNullable(): bool;

    public function getType(): string;

    public function getDefaultValue(): ?string;

    public function getLength(): ?int;
}
