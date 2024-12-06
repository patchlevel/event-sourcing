<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

final class SubscriptionCollectionTest extends TestCase
{
    public function testEmpty(): void
    {
        $collection = new SubscriptionCollection([]);

        self::assertCount(0, $collection);
        self::assertEquals([], iterator_to_array($collection));
        self::assertEquals(0, $collection->lowestPosition());
    }

    public function testSomeSubscription(): void
    {
        $subscription1 = new Subscription('foo', position: 5);
        $subscription2 = new Subscription('bar', position: 10);

        $collection = new SubscriptionCollection([$subscription1, $subscription2]);

        self::assertCount(2, $collection);
        self::assertEquals([$subscription1, $subscription2], iterator_to_array($collection));
        self::assertEquals(5, $collection->lowestPosition());
    }

    public function testRemove(): void
    {
        $subscription1 = new Subscription('foo', position: 5);
        $subscription2 = new Subscription('bar', position: 10);

        $collection = new SubscriptionCollection([$subscription1, $subscription2]);
        $collection->remove($subscription1);

        self::assertCount(1, $collection);
        self::assertEquals([$subscription2], iterator_to_array($collection));
        self::assertEquals(10, $collection->lowestPosition());
    }
}
