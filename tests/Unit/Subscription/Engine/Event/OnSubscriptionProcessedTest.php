<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionProcessed;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnSubscriptionProcessed::class)]
final class OnSubscriptionProcessedTest extends TestCase
{
    public function testInstantiate(): void
    {
        $subscription = new Subscription('foo');

        $event = new OnSubscriptionProcessed($subscription, 42);

        self::assertSame($subscription, $event->subscription);
        self::assertSame(42, $event->lastIndex);
        self::assertSame([], $event->errors);
    }
}
