<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NameChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

use function microtime;

/** @covers \Patchlevel\EventSourcing\EventBus\DefaultEventBus */
final class DefaultEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatchEvent(): void
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

        $eventBus = new DefaultEventBus($provider->reveal());
        $eventBus->dispatch($message);

        self::assertSame($message, $listener->message);
    }

    public function testDispatchEventWithSubscribe(): void
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

        $eventBus = DefaultEventBus::create([$listener]);
        $eventBus->dispatch($message);

        self::assertSame($message, $listener->message);
    }

    public function testDispatchMultipleMessages(): void
    {
        $listener = new class {
            /** @var list<Message> */
            public array $message = [];

            public function __invoke(Message $message): void
            {
                $this->message[] = $message;
            }
        };

        $message1 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $message2 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $provider = $this->prophesize(ListenerProvider::class);
        $provider->listenersForEvent(ProfileCreated::class)->willReturn([new ListenerDescriptor($listener->__invoke(...))]);
        $provider->listenersForEvent(ProfileCreated::class)->willReturn([new ListenerDescriptor($listener->__invoke(...))]);

        $eventBus = new DefaultEventBus($provider->reveal());
        $eventBus->dispatch($message1, $message2);

        self::assertCount(2, $listener->message);
        self::assertSame($message1, $listener->message[0]);
        self::assertSame($message2, $listener->message[1]);
    }

    public function testSynchroneEvents(): void
    {
        $messageA = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $messageB = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $provider = $this->prophesize(ListenerProvider::class);
        $eventBus = new DefaultEventBus($provider->reveal());

        $listenerA = new class ($eventBus, $messageB) {
            public float|null $time = null;

            public function __construct(
                private DefaultEventBus $bus,
                private Message $message,
            ) {
            }

            public function __invoke(Message $message): void
            {
                if (!$message->event() instanceof ProfileCreated) {
                    return;
                }

                $this->bus->dispatch($this->message);

                $this->time = microtime(true);
            }
        };

        $listenerB = new class {
            public float|null $time = null;

            public function __invoke(Message $message): void
            {
                if (!$message->event() instanceof ProfileVisited) {
                    return;
                }

                $this->time = microtime(true);
            }
        };

        $provider->listenersForEvent(ProfileCreated::class)->willReturn([new ListenerDescriptor($listenerA->__invoke(...))]);
        $provider->listenersForEvent(ProfileVisited::class)->willReturn([new ListenerDescriptor($listenerB->__invoke(...))]);

        $eventBus->dispatch($messageA);

        self::assertNotNull($listenerA->time);
        self::assertNotNull($listenerB->time);

        self::assertTrue($listenerA->time < $listenerB->time);
    }

    public function testMultipleMessagesAddingNewEventInListener(): void
    {
        $messageA = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $messageB = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $messageC = new Message(
            new NameChanged(
                'name',
            ),
        );

        $provider = $this->prophesize(ListenerProvider::class);
        $eventBus = new DefaultEventBus($provider->reveal());

        $listenerA = new class ($eventBus, $messageC) {
            public float|null $time = null;

            public function __construct(
                private DefaultEventBus $bus,
                private Message $message,
            ) {
            }

            public function __invoke(Message $message): void
            {
                if (!$message->event() instanceof ProfileCreated) {
                    return;
                }

                $this->bus->dispatch($this->message);

                $this->time = microtime(true);
            }
        };

        $listenerB = new class {
            public float|null $time = null;

            public function __invoke(Message $message): void
            {
                if (!$message->event() instanceof NameChanged) {
                    return;
                }

                $this->time = microtime(true);
            }
        };

        $provider->listenersForEvent(ProfileCreated::class)->willReturn([new ListenerDescriptor($listenerA->__invoke(...))]);
        $provider->listenersForEvent(ProfileVisited::class)->willReturn([]);
        $provider->listenersForEvent(NameChanged::class)->willReturn([new ListenerDescriptor($listenerB->__invoke(...))]);

        $eventBus->dispatch($messageA, $messageB);

        self::assertNotNull($listenerA->time);
        self::assertNotNull($listenerB->time);

        self::assertTrue($listenerA->time < $listenerB->time);
    }
}
