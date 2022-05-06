<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

use function microtime;

/** @covers \Patchlevel\EventSourcing\EventBus\DefaultEventBus */
class DefaultEventBusTest extends TestCase
{
    public function testDispatchEvent(): void
    {
        $listener = new class implements Listener {
            public ?Message $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de')
            )
        );

        $eventBus = new DefaultEventBus([$listener]);
        $eventBus->dispatch($message);

        self::assertSame($message, $listener->message);
    }

    public function testDispatchMultipleMessages(): void
    {
        $listener = new class implements Listener {
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
                Email::fromString('info@patchlevel.de')
            )
        );

        $message2 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de')
            )
        );

        $eventBus = new DefaultEventBus([$listener]);
        $eventBus->dispatch($message1, $message2);

        self::assertCount(2, $listener->message);
        self::assertSame($message1, $listener->message[0]);
        self::assertSame($message2, $listener->message[1]);
    }

    public function testDynamicListener(): void
    {
        $listener = new class implements Listener {
            public ?Message $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de')
            )
        );

        $eventBus = new DefaultEventBus();
        $eventBus->addListener($listener);
        $eventBus->dispatch($message);

        self::assertSame($message, $listener->message);
    }

    public function testSynchroneEvents(): void
    {
        $messageA = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de')
            )
        );

        $eventBus = new DefaultEventBus();

        $listenerA = new class ($eventBus) implements Listener {
            public ?float $time = null;
            private DefaultEventBus $bus;

            public function __construct(DefaultEventBus $bus)
            {
                $this->bus = $bus;
            }

            public function __invoke(Message $message): void
            {
                if (!$message->event() instanceof ProfileCreated) {
                    return;
                }

                $messageB = new Message(
                    new ProfileVisited(
                        ProfileId::fromString('1'),
                    )
                );

                $this->bus->dispatch($messageB);

                $this->time = microtime(true);
            }
        };

        $listenerB = new class implements Listener {
            public ?float $time = null;

            public function __invoke(Message $message): void
            {
                if (!$message->event() instanceof ProfileVisited) {
                    return;
                }

                $this->time = microtime(true);
            }
        };

        $eventBus->addListener($listenerA);
        $eventBus->addListener($listenerB);

        $eventBus->dispatch($messageA);

        self::assertNotNull($listenerA->time);
        self::assertNotNull($listenerB->time);

        self::assertTrue($listenerA->time < $listenerB->time);
    }
}
