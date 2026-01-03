<?php

namespace Articulate\Modules\Generators\Strategies;

use Articulate\Modules\Generators\GeneratorStrategyInterface;

/**
 * Example strategy for generating prefixed IDs.
 * Useful for multi-tenant applications or custom ID formats.
 */
class PrefixedIdGenerator implements GeneratorStrategyInterface {
    public function __construct(
        private readonly string $prefix = '',
        private readonly int $length = 8,
    ) {
    }

    public function generate(string $entityClass, array $options = []): mixed
    {
        $prefix = $options['prefix'] ?? $this->prefix;
        $length = $options['length'] ?? $this->length;

        // Generate a random string of specified length
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $prefix . $randomString;
    }

    public function getName(): string
    {
        return 'prefixed_id';
    }

    public function supports(string $generatorType): bool
    {
        return $generatorType === 'prefixed_id';
    }
}
