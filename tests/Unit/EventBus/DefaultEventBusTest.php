<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

use function microtime;

class DefaultEventBusTest extends TestCase
{
    public function testDispatchEvent(): void
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

        $eventBus = new DefaultEventBus([$listener]);
        $eventBus->dispatch($event);

        self::assertEquals($event, $listener->event);
    }

    public function testDynamicListener(): void
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

        $eventBus = new DefaultEventBus();
        $eventBus->addListener($listener);
        $eventBus->dispatch($event);

        self::assertEquals($event, $listener->event);
    }

    public function testSynchroneEvents(): void
    {
        $eventA = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('d.badura@gmx.de')
        );

        $eventBus = new DefaultEventBus();

        $listenerA = new class ($eventBus) implements Listener {
            public ?float $time = null;
            private DefaultEventBus $bus;

            public function __construct(DefaultEventBus $bus)
            {
                $this->bus = $bus;
            }

            public function __invoke(AggregateChanged $event): void
            {
                if (!$event instanceof ProfileCreated) {
                    return;
                }

                $eventB = ProfileVisited::raise(
                    ProfileId::fromString('1'),
                    ProfileId::fromString('1'),
                );

                $this->bus->dispatch($eventB);

                $this->time = microtime(true);
            }
        };

        $listenerB = new class implements Listener {
            public ?float $time = null;

            public function __invoke(AggregateChanged $event): void
            {
                if (!$event instanceof ProfileVisited) {
                    return;
                }

                $this->time = microtime(true);
            }
        };

        $eventBus->addListener($listenerA);
        $eventBus->addListener($listenerB);

        $eventBus->dispatch($eventA);

        self::assertNotNull($listenerA->time);
        self::assertNotNull($listenerB->time);

        self::assertTrue($listenerA->time < $listenerB->time);
    }
}
