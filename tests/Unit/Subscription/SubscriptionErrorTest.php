<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SubscriptionError::class)]
final class SubscriptionErrorTest extends TestCase
{
    public function testCreate(): void
    {
        $error = SubscriptionError::fromThrowable(
            Status::Active,
            new RuntimeException('foo bar'),
        );

        self::assertSame('foo bar', $error->errorMessage);
        self::assertSame(Status::Active, $error->previousStatus);
        self::assertIsArray($error->errorContext);
    }
}
