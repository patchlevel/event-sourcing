<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchShouldFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Throwable;

use function count;

#[Subscriber(BatchingSubscriber::ID, RunMode::FromBeginning)]
final class BatchingSubscriber
{
    public const ID = 'test';

    /** @var list<Message> */
    public array $receivedMessages = [];

    public int $beginBatchCalled = 0;
    public int $flushCalled = 0;
    public int $rollbackCalled = 0;

    public function __construct(
        public readonly Throwable|null $throwForMessage = null,
        public readonly Throwable|null $throwForBeginBatch = null,
        public readonly Throwable|null $throwForFlush = null,
        public readonly Throwable|null $throwForRollback = null,
        public readonly int $flushAfterMessages = 1_000,
    ) {
    }

    #[BatchBegin]
    public function begin(): BatchingState
    {
        $this->beginBatchCalled++;

        if ($this->throwForBeginBatch !== null) {
            throw $this->throwForBeginBatch;
        }

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

        if ($this->throwForMessage !== null) {
            throw $this->throwForMessage;
        }
    }

    #[BatchFlush]
    public function flush(BatchingState $state): void
    {
        $this->flushCalled++;

        if ($this->throwForFlush !== null) {
            throw $this->throwForFlush;
        }
    }

    #[BatchShouldFlush]
    public function shouldFlush(BatchingState $state): bool
    {
        return $this->flushAfterMessages <= count($state->messages);
    }

    #[BatchRollback]
    public function rollback(BatchingState $state): void
    {
        $this->rollbackCalled++;

        if ($this->throwForRollback !== null) {
            throw $this->throwForRollback;
        }
    }
}
