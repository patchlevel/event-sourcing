<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\ClassIsNotAnEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ClassIsNotAnEvent::class)]
final class ClassIsNotAnEventTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ClassIsNotAnEvent(ProfileCreated::class);

        self::assertSame(
            sprintf('class %s is not an event', ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
