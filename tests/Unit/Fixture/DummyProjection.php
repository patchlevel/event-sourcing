<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\Projection;

class DummyProjection implements Projection
{
    public static ?AggregateChanged $handledEvent = null;
    public static bool $createCalled = false;
    public static bool $dropCalled = false;

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event): void
    {
        self::$handledEvent = $event;
    }

    #[Create]
    public function create(): void
    {
        self::$createCalled = true;
    }

    #[Drop]
    public function drop(): void
    {
        self::$dropCalled = true;
    }
}
