<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\RetryStrategy;

use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryStrategyNotFound::class)]
final class RetryStrategyNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new RetryStrategyNotFound('foo');

        self::assertSame(
            'Retry strategy with name "foo" not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
