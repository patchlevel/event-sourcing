<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message as EventMessage;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\Projector;

final class DummyProjection implements Projector
{
    public EventMessage|null $handledMessage = null;
    public bool $createCalled = false;
    public bool $dropCalled = false;

    public function targetProjection(): ProjectionId
    {
        return new ProjectionId('dummy', 1);
    }

    #[Subscribe(ProfileCreated::class)]
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
