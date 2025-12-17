<?php

namespace Articulate\Attributes\Reflection;

interface RelationInterface
{
    public function getTargetEntity(): ?string;

    public function getDeclaringClassName(): string;

    public function isOwningSide(): bool;

    public function getMappedBy(): ?string;

    public function getInversedBy(): ?string;

    public function getPropertyName(): string;
}
