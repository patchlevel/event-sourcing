<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\AttributeListenerProvider */
final class AttributeListenerProviderTest extends TestCase
{
    public function testProvideNothing(): void
    {
        $eventBus = new AttributeListenerProvider([]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

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

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

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

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

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

        $eventBus = new AttributeListenerProvider([$listener1, $listener2]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

        self::assertEquals([
            new ListenerDescriptor($listener1->__invoke(...)),
            new ListenerDescriptor($listener2->__invoke(...)),
        ], $listeners);
    }

    public function testSubscribeAll(): void
    {
        $listener = new class {
            #[Subscribe('*')]
            public function __invoke(Message $message): void
            {
            }
        };

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

        self::assertEquals([
            new ListenerDescriptor($listener->__invoke(...)),
        ], $listeners);
    }

    public function testMixedSubscribeTypes(): void
    {
        $listener = new class {
            #[Subscribe('*')]
            public function foo(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function bar(Message $message): void
            {
            }
        };

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

        self::assertEquals([
            new ListenerDescriptor($listener->bar(...)),
            new ListenerDescriptor($listener->foo(...)),
        ], $listeners);
    }

    public function testCaching(): void
    {
        $listener = new class {
            #[Subscribe('*')]
            public function foo(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function bar(Message $message): void
            {
            }
        };

        $eventBus = new AttributeListenerProvider([$listener]);
        $listeners = $eventBus->listenersForEvent(ProfileCreated::class);

        self::assertEquals([
            new ListenerDescriptor($listener->bar(...)),
            new ListenerDescriptor($listener->foo(...)),
        ], $listeners);

        $cachedListeners = $eventBus->listenersForEvent(ProfileCreated::class);
        self::assertSame($listeners, $cachedListeners);
    }
}
