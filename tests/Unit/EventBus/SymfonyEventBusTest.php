<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SymfonyEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatchEvent(): void
    {
        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('d.badura@gmx.de')
        );

        $envelope = new Envelope($event);

        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(static function ($envelope) use ($event) {
            if (!$envelope instanceof Envelope) {
                return false;
            }

            return $envelope->getMessage() === $event;
        }))->willReturn($envelope)->shouldBeCalled();

        $eventBus = new SymfonyEventBus($messageBus->reveal());
        $eventBus->dispatch($event);
    }

    public function testDefaultEventBus(): void
    {
        $listener = new class implements Listener {
            public ?AggregateChanged $event = null;

            public function __invoke(AggregateChanged $event): void
            {
                $this->event = $event;
            }
        };

        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('d.badura@gmx.de')
        );

        $eventBus = SymfonyEventBus::create([$listener]);
        $eventBus->dispatch($event);

        self::assertEquals($event, $listener->event);
    }
}
