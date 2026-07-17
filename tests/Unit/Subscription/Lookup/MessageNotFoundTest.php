<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Lookup;

use Patchlevel\EventSourcing\Subscription\Lookup\MessageNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageNotFound::class)]
final class MessageNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MessageNotFound();

        self::assertSame(
            'Message not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
