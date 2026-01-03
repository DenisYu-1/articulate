<?php

namespace Articulate\Attributes\Relations;

interface RelationAttributeInterface {
    public function getTargetEntity(): ?string;

    public function getColumn(): ?string;
}
