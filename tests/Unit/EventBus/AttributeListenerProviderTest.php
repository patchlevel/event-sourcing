<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\AttributeListenerProvider */
final class AttributeListenerProviderTest extends TestCase
{
    public function testProvideNothing(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $eventBus = new AttributeListenerProvider([]);
        $listeners = $eventBus->listenersForEvent($event);

        self::assertSame([], $listeners);
    }

    public function testProvideMatchOneListener(): void
    {
        $listener = new class {
            #[Subscribe(ProfileCreated::class)]
            public function foo(Message $message): void
            {
            }

            #[Subscribe(ProfileVisited::class)]
            public function bar(Message $message): void
            {
            }
        };

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent($event);

        self::assertEquals([new ListenerDescriptor($listener->foo(...))], $listeners);
    }

    public function testFindMultipleMethods(): void
    {
        $listener = new class {
            #[Subscribe(ProfileCreated::class)]
            public function foo(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function bar(Message $message): void
            {
            }
        };

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent($event);

        self::assertEquals([
            new ListenerDescriptor($listener->foo(...)),
            new ListenerDescriptor($listener->bar(...)),
        ], $listeners);
    }

    public function testMultipleListener(): void
    {
        $listener1 = new class {
            #[Subscribe(ProfileCreated::class)]
            public function __invoke(Message $message): void
            {
            }
        };

        $listener2 = new class {
            #[Subscribe(ProfileCreated::class)]
            public function __invoke(Message $message): void
            {
            }
        };

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $eventBus = new AttributeListenerProvider([$listener1, $listener2]);
        $listeners = $eventBus->listenersForEvent($event);

        self::assertEquals([
            new ListenerDescriptor($listener1->__invoke(...)),
            new ListenerDescriptor($listener2->__invoke(...)),
        ], $listeners);
    }
}
