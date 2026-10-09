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

use function count;

#[Subscriber(AfterMessagesBatchingSubscriber::ID, RunMode::FromBeginning)]
final class AfterMessagesBatchingSubscriber
{
    public const ID = 'after-messages';

    /** @var list<Message> */
    public array $receivedMessages = [];

    /** @var list<int> number of messages contained in each flushed batch */
    public array $flushedBatchSizes = [];

    public int $beginBatchCalled = 0;
    public int $flushCalled = 0;

    #[BatchBegin]
    public function begin(): BatchingState
    {
        $this->beginBatchCalled++;

        return new BatchingState();
    }

    #[Subscribe(ProfileVisited::class)]
    public function handle(
        Message $message,
        #[BatchState]
        BatchingState $state,
    ): void {
        $state->messages[] = $message;
        $this->receivedMessages[] = $message;
    }

    #[BatchFlush(afterMessages: 2)]
    public function flush(BatchingState $state): void
    {
        $this->flushCalled++;
        $this->flushedBatchSizes[] = count($state->messages);
    }
}
