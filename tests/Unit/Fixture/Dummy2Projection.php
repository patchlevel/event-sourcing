<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\Projection;

class Dummy2Projection implements Projection
{
    public ?AggregateChanged $handledEvent = null;
    public bool $createCalled = false;
    public bool $dropCalled = false;

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event): void
    {
        $this->handledEvent = $event;
    }

    #[Create]
    public function create(): void
    {
        $this->createCalled = true;
    }

    #[Drop]
    public function drop(): void
    {
        $this->dropCalled = true;
    }
}
