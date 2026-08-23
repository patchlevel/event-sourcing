<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnSubscriptionRemoved::class)]
final class OnSubscriptionRemovedTest extends TestCase
{
    public function testInstantiate(): void
    {
        $subscription = new Subscription('foo');

        $event = new OnSubscriptionRemoved($subscription);

        self::assertSame($subscription, $event->subscription);
    }
}
