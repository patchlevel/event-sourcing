<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Outbox\OutboxPublisher;
use Patchlevel\EventSourcing\Outbox\OutboxStore;
use Patchlevel\EventSourcing\Outbox\StoreOutboxProcessor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Outbox\StoreOutboxProcessor */
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

        $eventBus = $this->prophesize(OutboxPublisher::class);
        $eventBus->publish($message)->shouldBeCalled();

        $consumer = new StoreOutboxProcessor($store->reveal(), $eventBus->reveal());
        $consumer->process();
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

        $eventBus = $this->prophesize(OutboxPublisher::class);
        $eventBus->publish($message)->shouldBeCalled();

        $consumer = new StoreOutboxProcessor($store->reveal(), $eventBus->reveal());
        $consumer->process(100);
    }
}
