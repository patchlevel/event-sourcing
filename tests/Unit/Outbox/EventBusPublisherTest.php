<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Outbox\EventBusPublisher;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Outbox\EventBusPublisher */
final class EventBusPublisherTest extends TestCase
{
    use ProphecyTrait;

    public function testPublish(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $eventBus = $this->prophesize(Consumer::class);
        $eventBus->consume($message)->shouldBeCalled();

        $publisher = new EventBusPublisher($eventBus->reveal());
        $publisher->publish($message);
    }
}
