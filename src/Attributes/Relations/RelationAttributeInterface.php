<?php

namespace Norm\Attributes\Relations;

interface RelationAttributeInterface
{
    public function getTargetEntity(): ?string;
    public function getColumn(): ?string;
}
