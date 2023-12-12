<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Outbox\StoreOutboxConsumer;
use Patchlevel\EventSourcing\Store\OutboxStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Outbox\StoreOutboxConsumer */
final class StoreOutboxConsumerTest extends TestCase
{
    use ProphecyTrait;

    public function testConsume(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $store = $this->prophesize(OutboxStore::class);
        $store->retrieveOutboxMessages(null)->willReturn([$message]);
        $store->markOutboxMessageConsumed($message)->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($message)->shouldBeCalled();

        $consumer = new StoreOutboxConsumer($store->reveal(), $eventBus->reveal());
        $consumer->consume();
    }

    public function testConsumeWithLimit(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $store = $this->prophesize(OutboxStore::class);
        $store->retrieveOutboxMessages(100)->willReturn([$message]);
        $store->markOutboxMessageConsumed($message)->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($message)->shouldBeCalled();

        $consumer = new StoreOutboxConsumer($store->reveal(), $eventBus->reveal());
        $consumer->consume(100);
    }
}
