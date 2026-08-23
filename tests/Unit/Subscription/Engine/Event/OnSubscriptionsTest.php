<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptions;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnSubscriptions::class)]
final class OnSubscriptionsTest extends TestCase
{
    public function testInstantiate(): void
    {
        $criteria = new SubscriptionEngineCriteria(['foo']);

        $event = new OnSubscriptions($criteria);

        self::assertSame($criteria, $event->criteria);
    }
}
