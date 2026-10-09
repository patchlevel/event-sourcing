<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventNameNotRegistered;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventNameNotRegistered::class)]
final class EventNameNotRegisteredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new EventNameNotRegistered('profile.created');

        self::assertSame(
            'Event name "profile.created" is not registered',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
