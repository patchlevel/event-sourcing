<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\RetryStrategy;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\RetryStrategy\NoRetryStrategy;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\RetryStrategy\NoRetryStrategy */
final class NoRetryStrategyTest extends TestCase
{
    public function testNull(): void
    {
        $strategy = new NoRetryStrategy();

        self::assertFalse($strategy->shouldRetry(
            new Projection('test'),
        ));
    }
}
