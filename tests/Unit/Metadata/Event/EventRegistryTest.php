<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\Event\EventNameNotRegistered;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

final class EventRegistryTest extends TestCase
{
    public function testEmpty(): void
    {
        $registry = new EventRegistry([]);

        self::assertFalse($registry->hasEventClass('Foo'));
        self::assertCount(0, $registry->eventClasses());
    }

    public function testMapping(): void
    {
        $registry = new EventRegistry(['profile.created' => ProfileCreated::class]);

        self::assertTrue($registry->hasEventClass(ProfileCreated::class));
        self::assertTrue($registry->hasEventName('profile.created'));
        self::assertEquals('profile.created', $registry->eventName(ProfileCreated::class));
        self::assertEquals(ProfileCreated::class, $registry->eventClass('profile.created'));
        self::assertEquals(['profile.created' => ProfileCreated::class], $registry->eventClasses());
        self::assertEquals([ProfileCreated::class => 'profile.created'], $registry->eventNames());
    }

    public function testEventClassNotRegistered(): void
    {
        $this->expectException(EventClassNotRegistered::class);

        $registry = new EventRegistry([]);
        $registry->eventName(ProfileCreated::class);
    }

    public function testEventNameNotRegistered(): void
    {
        $this->expectException(EventNameNotRegistered::class);

        $registry = new EventRegistry([]);
        $registry->eventClass('profile.created');
    }
}
