<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Modules\QueryBuilder\Cursor;
use Articulate\Modules\QueryBuilder\CursorCodec;
use Articulate\Modules\QueryBuilder\CursorDirection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CursorCodecTest extends TestCase {
    private CursorCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new CursorCodec();
    }

    public function testEncodeAndDecode(): void
    {
        $cursor = new Cursor(['id' => 42, 'name' => 'test'], CursorDirection::NEXT);

        $token = $this->codec->encode($cursor);
        $decoded = $this->codec->decode($token);

        $this->assertSame($cursor->getValues(), $decoded->getValues());
        $this->assertSame($cursor->getDirection(), $decoded->getDirection());
    }

    public function testEncodeAndDecodeWithPrevDirection(): void
    {
        $cursor = new Cursor(['score' => 99.5], CursorDirection::PREV);

        $token = $this->codec->encode($cursor);
        $decoded = $this->codec->decode($token);

        $this->assertSame($cursor->getValues(), $decoded->getValues());
        $this->assertSame(CursorDirection::PREV, $decoded->getDirection());
    }

    public function testDecodeInvalidToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->codec->decode('not-a-valid-token!!!');
    }

    public function testDecodeEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->codec->decode('');
    }
}
