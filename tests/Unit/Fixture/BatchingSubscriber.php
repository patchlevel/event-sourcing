<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Throwable;

use function count;

#[Subscriber(BatchingSubscriber::ID, RunMode::FromBeginning)]
final class BatchingSubscriber implements BatchableSubscriber
{
    public const ID = 'test';

    /** @var list<Message> */
    public array $receivedMessages = [];

    public int $beginBatchCalled = 0;
    public int $commitBatchCalled = 0;
    public int $rollbackBatchCalled = 0;

    public function __construct(
        public readonly Throwable|null $throwForMessage = null,
        public readonly Throwable|null $throwForBeginBatch = null,
        public readonly Throwable|null $throwForCommitBatch = null,
        public readonly Throwable|null $throwForRollbackBatch = null,
        public readonly int $forceCommitAfterMessages = 1_000,
    ) {
    }

    #[Subscribe(ProfileVisited::class)]
    public function handle(Message $message): void
    {
        $this->receivedMessages[] = $message;

        if ($this->throwForMessage !== null) {
            throw $this->throwForMessage;
        }
    }

    public function beginBatch(): void
    {
        $this->beginBatchCalled++;

        if ($this->throwForBeginBatch !== null) {
            throw $this->throwForBeginBatch;
        }
    }

    public function commitBatch(): void
    {
        $this->commitBatchCalled++;

        if ($this->throwForCommitBatch !== null) {
            throw $this->throwForCommitBatch;
        }
    }

    public function rollbackBatch(): void
    {
        $this->rollbackBatchCalled++;

        if ($this->throwForRollbackBatch !== null) {
            throw $this->throwForRollbackBatch;
        }
    }

    public function forceCommit(): bool
    {
        return $this->forceCommitAfterMessages <= count($this->receivedMessages);
    }
}
