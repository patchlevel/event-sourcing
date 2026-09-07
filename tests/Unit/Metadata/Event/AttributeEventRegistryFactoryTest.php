<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventAlreadyInRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\AliasFixture\EventWithAlias;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeEventRegistryFactory::class)]
final class AttributeEventRegistryFactoryTest extends TestCase
{
    public function testCreateRegistry(): void
    {
        $factory = new AttributeEventRegistryFactory();
        $registry = $factory->create([__DIR__ . '/../../Fixture']);

        self::assertTrue($registry->hasEventClass(ProfileCreated::class));
        self::assertFalse($registry->hasEventClass(Message::class));
    }

    public function testCreateRegistryWithDuplicateEventName(): void
    {
        $this->expectException(EventAlreadyInRegistry::class);
        $this->expectExceptionMessage('The event name "email_changed" is already used in the registry. Maybe you defined 2 events with the same name.');

        $factory = new AttributeEventRegistryFactory();
        $factory->create([__DIR__ . '/Fixture']);
    }

    public function testCreateRegistryWithAliases(): void
    {
        $factory = new AttributeEventRegistryFactory();
        $registry = $factory->create([__DIR__ . '/AliasFixture']);

        self::assertTrue($registry->hasEventName('event_with_alias'));
        self::assertTrue($registry->hasEventName('event_alias'));
        self::assertSame(EventWithAlias::class, $registry->eventClass('event_alias'));
    }

    public function testCreateRegistryWithDuplicateAlias(): void
    {
        $this->expectException(EventAlreadyInRegistry::class);
        $this->expectExceptionMessage('The event name "duplicate_alias" is already used in the registry. Maybe you defined 2 events with the same name.');

        $factory = new AttributeEventRegistryFactory();
        $factory->create([__DIR__ . '/DuplicateAliasFixture']);
    }
}
