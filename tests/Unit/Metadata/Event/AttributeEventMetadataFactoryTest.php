<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\ClassIsNotAnEvent;
use PHPUnit\Framework\TestCase;

final class AttributeEventMetadataFactoryTest extends TestCase
{
    public function testEmptyEvent(): void
    {
        $this->expectException(ClassIsNotAnEvent::class);

        $event = new class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testEvent(): void
    {
        $event = new #[Event('profile_created')] class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
    }
}
