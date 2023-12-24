<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\DuplicateSubscribeMethod;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\Subscriber */
final class SubscriberTest extends TestCase
{
    public function testSubscribeEvent(): void
    {
        $subscriber = new class extends Subscriber {
            public Message|null $message = null;

            #[Subscribe(ProfileCreated::class)]
            public function handle(Message $message): void
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

        $eventBus = new DefaultEventBus([$subscriber]);
        $eventBus->dispatch($message);

        self::assertSame($message, $subscriber->message);
    }

    public function testSubscribeWrongEvent(): void
    {
        $subscriber = new class extends Subscriber {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
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

        $eventBus = new DefaultEventBus([$subscriber]);
        $eventBus->dispatch($message);

        self::assertNull($subscriber->message);
    }

    public function testSubscribeMultipleEvents(): void
    {
        $subscriber = new class extends Subscriber {
            public Message|null $a = null;
            public Message|null $b = null;

            #[Subscribe(ProfileCreated::class)]
            public function handleA(Message $message): void
            {
                $this->a = $message;
            }

            #[Subscribe(ProfileVisited::class)]
            public function handleB(Message $message): void
            {
                $this->b = $message;
            }
        };

        $message1 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $message2 = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $eventBus = new DefaultEventBus([$subscriber]);
        $eventBus->dispatch($message1, $message2);

        self::assertSame($message1, $subscriber->a);
        self::assertSame($message2, $subscriber->b);
    }

    public function testSubscribeMultipleEventsOnSameMethod(): void
    {
        $subscriber = new class extends Subscriber {
            /** @var list<Message> */
            public array $messages = [];

            #[Subscribe(ProfileCreated::class)]
            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->messages[] = $message;
            }
        };

        $message1 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $message2 = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $eventBus = new DefaultEventBus([$subscriber]);
        $eventBus->dispatch($message1, $message2);

        self::assertCount(2, $subscriber->messages);
        self::assertSame($message1, $subscriber->messages[0]);
        self::assertSame($message2, $subscriber->messages[1]);
    }

    public function testDuplicatedEvents(): void
    {
        $this->expectException(DuplicateSubscribeMethod::class);

        $subscriber = new class extends Subscriber {
            #[Subscribe(ProfileCreated::class)]
            public function handleA(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function handleB(Message $message): void
            {
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $eventBus = new DefaultEventBus();
        $eventBus->addListener($subscriber);
        $eventBus->dispatch($message);
    }
}
