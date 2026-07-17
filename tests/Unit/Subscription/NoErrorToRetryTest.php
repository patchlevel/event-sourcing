<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Patchlevel\EventSourcing\Subscription\NoErrorToRetry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoErrorToRetry::class)]
final class NoErrorToRetryTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new NoErrorToRetry();

        self::assertSame(
            'No error to retry.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
