<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\DefaultConsumer */
final class DefaultConsumerTest extends TestCase
{
    use ProphecyTrait;

    public function testConsumeEvent(): void
    {
        $listener = new class {
            public Message|null $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $provider = $this->prophesize(ListenerProvider::class);
        $provider->listenersForEvent(ProfileCreated::class)->willReturn([new ListenerDescriptor($listener->__invoke(...))]);

        $eventBus = new DefaultConsumer($provider->reveal());
        $eventBus->consume($message);

        self::assertSame($message, $listener->message);
    }

    public function testConsumeWithSubscribe(): void
    {
        $listener = new class {
            public Message|null $message = null;

            #[Subscribe(ProfileCreated::class)]
            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $eventBus = DefaultConsumer::create([$listener]);
        $eventBus->consume($message);

        self::assertSame($message, $listener->message);
    }
}
