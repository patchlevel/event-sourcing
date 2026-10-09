<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventClassNotRegistered;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(EventClassNotRegistered::class)]
final class EventClassNotRegisteredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new EventClassNotRegistered(ProfileCreated::class);

        self::assertSame(
            sprintf('Event class "%s" is not registered', ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
