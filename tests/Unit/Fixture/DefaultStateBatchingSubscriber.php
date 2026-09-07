<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber(DefaultStateBatchingSubscriber::ID, RunMode::FromBeginning)]
final class DefaultStateBatchingSubscriber
{
    public const ID = 'default-state';

    public object|null $receivedState = null;

    public object|null $flushedState = null;

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
        $this->flushedState = $state;
    }
}
