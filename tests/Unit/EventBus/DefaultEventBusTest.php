<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use LogicException;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NameChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultEventBus::class)]
final class DefaultEventBusTest extends TestCase
{
    public function testDispatchEvent(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $consumer = $this->createMock(Consumer::class);
        $consumer->expects($this->atLeastOnce())->method('consume')->with($message);

        $eventBus = new DefaultEventBus($consumer);
        $eventBus->dispatch($message);
    }

    public function testDispatchMultipleMessages(): void
    {
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

        $consumer = $this->createMock(Consumer::class);
        $consumer->expects($this->atLeastOnce())->method('consume')->with($message1);
        $consumer->expects($this->atLeastOnce())->method('consume')->with($message2);

        $eventBus = new DefaultEventBus($consumer);
        $eventBus->dispatch($message1, $message2);
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

        $consumer = $this->createMock(Consumer::class);
        $eventBus = new DefaultEventBus($consumer);

        $consumer->expects($this->exactly(3))->method('consume')->willReturnCallback(
            static fn (Message $message) => match ($message) {
                $messageA => $eventBus->dispatch($messageC),
                $messageB => true,
                $messageC => true,
                default => throw new LogicException('Unmatched case!'),
            },
        );

        $eventBus->dispatch($messageA, $messageB);
    }

    public function testCreate(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $listener = new class implements Consumer {
            public Message|null $message = null;

            #[Subscribe(ProfileCreated::class)]
            public function consume(Message $message): void
            {
                $this->message = $message;
            }
        };

        $eventBus = DefaultEventBus::create([$listener]);
        $eventBus->dispatch($message);

        self::assertSame($message, $listener->message);
    }
}
