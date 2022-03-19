<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/** @covers \Patchlevel\EventSourcing\EventBus\SymfonyEventBus */
class SymfonyEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatchEvent(): void
    {
        $message = new Message(
            Profile::class,
            '1',
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.badura@gmx.de')
            )
        );

        $envelope = new Envelope($message);

        $symfony = $this->prophesize(MessageBusInterface::class);
        $symfony->dispatch(Argument::that(static function ($envelope) use ($message) {
            if (!$envelope instanceof Envelope) {
                return false;
            }

            return $envelope->getMessage() === $message;
        }))->willReturn($envelope)->shouldBeCalled();

        $eventBus = new SymfonyEventBus($symfony->reveal());
        $eventBus->dispatch($message);
    }

    public function testDefaultEventBus(): void
    {
        $listener = new class implements Listener {
            public ?Message $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            Profile::class,
            '1',
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.badura@gmx.de')
            )
        );

        $eventBus = SymfonyEventBus::create([$listener]);
        $eventBus->dispatch($message);

        self::assertSame($message, $listener->message);
    }
}
