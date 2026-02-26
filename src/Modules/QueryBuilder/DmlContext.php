<?php

namespace Articulate\Modules\QueryBuilder;

interface DmlContext {
    public function getEntityClass(): ?string;

    public function getFrom(): string;

    public function setEntityClass(string $entityClass): void;

    public function setFrom(string $from): void;

    public function addWhere(string $clause, mixed ...$values): void;
}
