<?php

namespace Articulate\Modules\DatabaseSchemaReader;

use Articulate\Utils\TypeRegistry;

class DatabaseColumn
{
    public readonly ?int $length;

    public readonly string $type;

    public readonly string $phpType;

    public function __construct(
        public readonly string $name,
        string $type,
        public readonly bool $isNullable,
        public readonly ?string $defaultValue,
        private readonly TypeRegistry $typeRegistry = new TypeRegistry(),
    ) {
        // Handle parameterized types like VARCHAR(255), TINYINT(1)
        if (preg_match('/^(\w+)\((\d+)\)$/', $type, $matches)) {
            $baseType = $matches[1];
            $this->length = (int) $matches[2];

            // Convert TINYINT(1) to bool
            if (strtoupper($baseType) === 'TINYINT' && $this->length === 1) {
                $this->type = 'TINYINT(1)';
                $this->phpType = 'bool';
            } else {
                $this->type = $baseType;
                $this->phpType = $this->typeRegistry->getPhpType($baseType);
            }

            return;
        }

        $this->type = $type;
        $this->length = null;
        $this->phpType = $this->typeRegistry->getPhpType($type);
    }
}
