<?php

namespace Norm\Modules\DatabaseSchemaReader;

class DatabaseColumn {
    public readonly ?int $length;
    public readonly string $type;
    public function __construct(
        public readonly string $name,
        string $type,
        public readonly bool $isNullable,
        public readonly ?string $defaultValue,
    ) {
        if (preg_match('/^(\w+)\((\d+)\)$/', $type, $matches)) {
            $this->type = 'string';
            $this->length = (int) $matches[2];
            return;
        }
        $this->type = $type;
        $this->length = null;
    }
}
