<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventMetadata::class)]
final class EventMetadataTest extends TestCase
{
    public function testInstantiate(): void
    {
        $metadata = new EventMetadata('profile.created', true, ['profile_created']);

        self::assertSame('profile.created', $metadata->name);
        self::assertTrue($metadata->splitStream);
        self::assertSame(['profile_created'], $metadata->aliases);
    }

    public function testInstantiateWithDefaults(): void
    {
        $metadata = new EventMetadata('profile.created');

        self::assertSame('profile.created', $metadata->name);
        self::assertFalse($metadata->splitStream);
        self::assertSame([], $metadata->aliases);
    }
}
