<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory */
final class AttributeEventRegistryFactoryTest extends TestCase
{
    public function testCreateRegistry(): void
    {
        $factory = new AttributeEventRegistryFactory();
        $registry = $factory->create([__DIR__ . '/../../Fixture']);

        self::assertTrue($registry->hasEventClass(ProfileCreated::class));
        self::assertFalse($registry->hasEventClass(Message::class));
    }
}
