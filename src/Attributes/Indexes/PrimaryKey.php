<?php

namespace Articulate\Attributes\Indexes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey {
    public const GENERATOR_AUTO_INCREMENT = 'auto_increment';

    public const GENERATOR_UUID_V4 = 'uuid_v4';

    public const GENERATOR_UUID_V7 = 'uuid_v7';

    public const GENERATOR_ULID = 'ulid';

    public const GENERATOR_SERIAL = 'serial';

    public const GENERATOR_BIGSERIAL = 'bigserial';

    public function __construct(
        public ?string $generator = null,
        public ?string $sequence = null,
        public ?array $options = null,
    ) {
    }
}
