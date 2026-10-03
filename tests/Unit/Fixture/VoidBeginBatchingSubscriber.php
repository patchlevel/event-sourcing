<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber(VoidBeginBatchingSubscriber::ID, RunMode::FromBeginning)]
final class VoidBeginBatchingSubscriber
{
    public const ID = 'void-begin';

    public bool $beginCalled = false;

    public object|null $receivedState = null;

    #[BatchBegin]
    public function begin(): void
    {
        $this->beginCalled = true;
    }

    #[Subscribe(ProfileVisited::class)]
    public function handle(
        Message $message,
        #[BatchState]
        object $state,
    ): void {
        $this->receivedState = $state;
    }

    #[BatchFlush]
    public function flush(object $state): void
    {
    }
}
