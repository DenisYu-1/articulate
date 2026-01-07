<?php

namespace Articulate\Modules\Database\SchemaReader;

use Articulate\Utils\TypeRegistry;

class DatabaseColumn {
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
        // Handle parameterized types like VARCHAR(255), NUMERIC(10,2)
        if (preg_match('/^(\w+)\(([^)]+)\)$/', $type, $matches)) {
            $baseType = $matches[1];
            $params = $matches[2];

            // Check if params contain comma (e.g., NUMERIC(10,2))
            if (strpos($params, ',') !== false) {
                $paramParts = explode(',', $params);
                $this->length = (int) trim($paramParts[0]);
                // For now, we don't handle scale separately, but we could extend this
            } else {
                $this->length = (int) $params;
            }

            // Special handling for TINYINT(1) -> keep full type for boolean mapping
            if (strtoupper($baseType) === 'TINYINT' && $this->length === 1) {
                $this->type = $type; // Keep "TINYINT(1)"
            } else {
                $this->type = $baseType; // Store base type like "VARCHAR"
            }

            $this->phpType = $this->typeRegistry->getPhpType($type); // Pass full type string for special handling

            return;
        }

        $this->type = $type;
        $this->length = null;
        $this->phpType = $this->typeRegistry->getPhpType($type);
    }
}
