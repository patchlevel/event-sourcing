<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventAlreadyInRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventAlreadyInRegistry::class)]
final class EventAlreadyInRegistryTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new EventAlreadyInRegistry('profile.created');

        self::assertSame(
            'The event name "profile.created" is already used in the registry. Maybe you defined 2 events with the same name.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
