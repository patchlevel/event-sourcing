<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use Patchlevel\EventSourcing\Message\StreamClosed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamClosed::class)]
final class StreamClosedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new StreamClosed();

        self::assertSame('Stream is already closed.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }
}
