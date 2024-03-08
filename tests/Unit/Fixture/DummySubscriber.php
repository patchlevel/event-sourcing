<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message as EventMessage;

#[Subscriber('dummy')]
final class DummySubscriber
{
    public EventMessage|null $handledMessage = null;
    public bool $createCalled = false;
    public bool $dropCalled = false;

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(EventMessage $message): void
    {
        $this->handledMessage = $message;
    }

    #[Setup]
    public function create(): void
    {
        $this->createCalled = true;
    }

    #[Teardown]
    public function drop(): void
    {
        $this->dropCalled = true;
    }
}
