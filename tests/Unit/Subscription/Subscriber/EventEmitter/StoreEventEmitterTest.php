<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\EventEmitter;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\StoreEventEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(StoreEventEmitter::class)]
final class StoreEventEmitterTest extends TestCase
{
    public function testEmitWritesToSubscriptionStream(): void
    {
        $store = new InMemoryStore();
        $emitter = new StoreEventEmitter($store, 'subscription_foo');

        $emitter->emit([new stdClass(), new stdClass()]);

        self::assertSame(['subscription_foo'], $store->streams());
        self::assertSame(2, $store->count(new Criteria(new StreamCriterion('subscription_foo'))));
    }

    public function testLinkToWritesToGivenStream(): void
    {
        $store = new InMemoryStore();
        $emitter = new StoreEventEmitter($store, 'subscription_foo');

        $emitter->linkTo('other_stream', [new stdClass()]);

        self::assertSame(['other_stream'], $store->streams());

        $message = $store->load(new Criteria(new StreamCriterion('other_stream')))->current();

        self::assertNotNull($message);
        self::assertSame('other_stream', $message->header(StreamNameHeader::class)->streamName);
    }

    public function testEmitWithoutEventsDoesNothing(): void
    {
        $store = new InMemoryStore();
        $emitter = new StoreEventEmitter($store, 'subscription_foo');

        $emitter->emit([]);

        self::assertSame([], $store->streams());
    }
}
