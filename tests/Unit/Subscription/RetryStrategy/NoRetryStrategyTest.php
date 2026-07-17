<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\RetryStrategy;

use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoRetryStrategy::class)]
final class NoRetryStrategyTest extends TestCase
{
    public function testNull(): void
    {
        $strategy = new NoRetryStrategy();

        self::assertFalse($strategy->shouldRetry(
            new Subscription('test'),
        ));
    }

    public function testCanRetry(): void
    {
        $strategy = new NoRetryStrategy();

        self::assertFalse($strategy->canRetry(
            new Subscription('test'),
        ));
    }
}
