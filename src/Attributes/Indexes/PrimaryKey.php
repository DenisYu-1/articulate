<?php

namespace Articulate\Attributes\Indexes;

use Articulate\Attributes\Property;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey extends Property {
    public const GENERATOR_AUTO_INCREMENT = 'auto_increment';

    public const GENERATOR_UUID_V4 = 'uuid_v4';

    public const GENERATOR_UUID_V7 = 'uuid_v7';

    public const GENERATOR_ULID = 'ulid';

    public const GENERATOR_SERIAL = 'serial';

    public const GENERATOR_BIGSERIAL = 'bigserial';

    public function __construct(
        ?string $name = null,
        ?string $type = null,
        ?bool $nullable = null,
        ?string $defaultValue = null,
        ?int $maxLength = null,
        public ?string $generator = null,
        public ?string $sequence = null,
        public ?array $options = null,
    ) {
        parent::__construct($name, $type, $nullable, $defaultValue, $maxLength);
    }
}
