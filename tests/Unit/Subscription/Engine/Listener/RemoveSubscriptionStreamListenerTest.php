<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RemoveSubscriptionStreamListener;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveSubscriptionStreamListener::class)]
final class RemoveSubscriptionStreamListenerTest extends TestCase
{
    public function testRemovesSubscriptionStream(): void
    {
        $store = $this->createMock(Store::class);
        $store->expects($this->once())
            ->method('remove')
            ->with(new Criteria(new StreamCriterion('subscription_foo')));

        $listener = new RemoveSubscriptionStreamListener($store);
        $listener(new OnSubscriptionRemoved(new Subscription('foo')));
    }
}
