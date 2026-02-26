<?php

namespace Articulate\Modules\QueryBuilder;

use InvalidArgumentException;

class CursorCodec {
    public function encode(Cursor $cursor): string
    {
        $data = [
            'values' => $cursor->getValues(),
            'direction' => $cursor->getDirection()->value,
        ];

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public function decode(string $token): Cursor
    {
        try {
            $json = base64_decode(strtr($token, '-_', '+/') . str_repeat('=', (4 - strlen($token) % 4) % 4), true);
            if ($json === false) {
                throw new InvalidArgumentException('Invalid cursor token: base64 decode failed');
            }

            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['values']) || !is_array($data['values'])) {
                throw new InvalidArgumentException('Invalid cursor token: missing or invalid values');
            }

            if (!isset($data['direction']) || !in_array($data['direction'], ['next', 'prev'], true)) {
                throw new InvalidArgumentException('Invalid cursor token: missing or invalid direction');
            }

            return new Cursor(
                $data['values'],
                CursorDirection::from($data['direction'])
            );
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid cursor token: JSON decode failed', 0, $e);
        }
    }
}
