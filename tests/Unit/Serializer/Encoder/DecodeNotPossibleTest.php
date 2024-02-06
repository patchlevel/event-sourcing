<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Encoder;

use Patchlevel\EventSourcing\Serializer\Encoder\DecodeNotPossible;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Serializer\Encoder\DecodeNotPossible */
final class DecodeNotPossibleTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DecodeNotPossible('foo');

        self::assertSame(
            'deserialization of "foo" data is not possible',
            $exception->getMessage(),
        );
        self::assertSame('foo', $exception->data());
        self::assertSame(0, $exception->getCode());
    }
}
