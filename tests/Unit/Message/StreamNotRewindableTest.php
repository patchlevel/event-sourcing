<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use Patchlevel\EventSourcing\Message\StreamNotRewindable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamNotRewindable::class)]
final class StreamNotRewindableTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new StreamNotRewindable();

        self::assertSame(
            'Stream cannot be rewound because the underlying iterator is single-pass.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
