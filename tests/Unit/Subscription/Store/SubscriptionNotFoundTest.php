<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound */
final class SubscriptionNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SubscriptionNotFound('foo-1');

        self::assertSame(
            'Subscription with the id "foo-1" not found.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
