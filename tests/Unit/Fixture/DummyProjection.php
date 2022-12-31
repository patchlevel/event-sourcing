<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message as EventMessage;
use Patchlevel\EventSourcing\Projection\Projector\Projector;

final class DummyProjection implements Projector
{
    public ?EventMessage $handledMessage = null;
    public bool $createCalled = false;
    public bool $dropCalled = false;

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(EventMessage $message): void
    {
        $this->handledMessage = $message;
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
