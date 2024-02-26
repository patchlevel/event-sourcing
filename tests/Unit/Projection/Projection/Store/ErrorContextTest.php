<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\ThrowableToErrorContextTransformer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ThrowableToErrorContextTransformer */
final class ErrorContextTest extends TestCase
{
    public function testWithoutPrevious(): void
    {
        $error = new RuntimeException('foo');

        $result = ThrowableToErrorContextTransformer::transform($error);

        self::assertCount(1, $result);
        self::assertSame('foo', $result[0]['message']);
        self::assertSame(0, $result[0]['code']);
        self::assertSame(__FILE__, $result[0]['file']);
    }

    public function testWithPrevious(): void
    {
        $error = new RuntimeException('foo', 0, new RuntimeException('bar'));

        $result = ThrowableToErrorContextTransformer::transform($error);

        self::assertCount(2, $result);
        self::assertSame('foo', $result[0]['message']);
        self::assertSame(0, $result[0]['code']);
        self::assertSame(__FILE__, $result[0]['file']);

        self::assertSame('bar', $result[1]['message']);
        self::assertSame(0, $result[1]['code']);
        self::assertSame(__FILE__, $result[1]['file']);
    }
}
