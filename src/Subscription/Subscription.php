<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

use DateTimeImmutable;
use Throwable;

use function str_ends_with;
use function strlen;
use function substr;

final class Subscription
{
    public const DEFAULT_GROUP = 'default';

    /** Separates the subscriber id from the partition key in the id of a partition. */
    public const PARTITION_SEPARATOR = '#';

    /** @param list<object>|null $cleanupTasks */
    public function __construct(
        private readonly string $id,
        private string $group = self::DEFAULT_GROUP,
        private RunMode $runMode = RunMode::FromBeginning,
        private Status $status = Status::New,
        private int|null $position = null,
        private SubscriptionError|null $error = null,
        private int $retryAttempt = 0,
        private DateTimeImmutable|null $lastSavedAt = null,
        private array|null $cleanupTasks = null,
        private readonly string|null $partitionKey = null,
    ) {
        if ($partitionKey !== null && !str_ends_with($id, self::PARTITION_SEPARATOR . $partitionKey)) {
            throw new InvalidPartitionId($id, $partitionKey);
        }
    }

    /**
     * Creates the partition of a partitioned subscriber. The partition belongs to the same subscriber as the given main
     * subscription and starts with its group, run mode and status. A failure of the main subscription is not the one of
     * the partition: if the main subscription is in error or failed, the partition starts with the status before.
     */
    public static function partition(self $main, string $partitionKey, int $position): self
    {
        if ($main->isPartition()) {
            throw new PartitionOfPartitionNotPossible($main->id());
        }

        $status = $main->status();

        if ($status === Status::Error || $status === Status::Failed) {
            $status = $main->subscriptionError()->previousStatus ?? Status::Active;
        }

        return new self(
            self::partitionId($main->id(), $partitionKey),
            $main->group(),
            $main->runMode(),
            $status,
            $position,
            partitionKey: $partitionKey,
        );
    }

    public static function partitionId(string $subscriberId, string $partitionKey): string
    {
        return $subscriberId . self::PARTITION_SEPARATOR . $partitionKey;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * The id of the subscriber, the same as the id for every subscription that is not a partition.
     */
    public function subscriberId(): string
    {
        if ($this->partitionKey === null) {
            return $this->id;
        }

        return substr($this->id, 0, -strlen(self::PARTITION_SEPARATOR . $this->partitionKey));
    }

    public function partitionKey(): string|null
    {
        return $this->partitionKey;
    }

    public function isPartition(): bool
    {
        return $this->partitionKey !== null;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function changeGroup(string $group): void
    {
        $this->group = $group;
    }

    public function runMode(): RunMode
    {
        return $this->runMode;
    }

    public function changeRunMode(RunMode $runMode): void
    {
        $this->runMode = $runMode;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function position(): int|null
    {
        return $this->position;
    }

    public function subscriptionError(): SubscriptionError|null
    {
        return $this->error;
    }

    public function changePosition(int $position): void
    {
        $this->position = $position;
    }

    public function new(): void
    {
        $this->status = Status::New;
        $this->error = null;
    }

    public function isNew(): bool
    {
        return $this->status === Status::New;
    }

    public function booting(): void
    {
        $this->status = Status::Booting;
        $this->error = null;
    }

    public function isBooting(): bool
    {
        return $this->status === Status::Booting;
    }

    public function active(): void
    {
        $this->status = Status::Active;
        $this->error = null;
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    public function pause(): void
    {
        $this->status = Status::Paused;
    }

    public function isPaused(): bool
    {
        return $this->status === Status::Paused;
    }

    public function finished(): void
    {
        $this->status = Status::Finished;
        $this->error = null;
    }

    public function isFinished(): bool
    {
        return $this->status === Status::Finished;
    }

    public function detached(): void
    {
        $this->status = Status::Detached;
    }

    public function isDetached(): bool
    {
        return $this->status === Status::Detached;
    }

    public function error(Throwable|string $error): void
    {
        $previousStatus = $this->status;
        $this->status = Status::Error;

        if ($error instanceof Throwable) {
            $this->error = SubscriptionError::fromThrowable($previousStatus, $error);

            return;
        }

        $this->error = new SubscriptionError($error, $previousStatus);
    }

    public function isError(): bool
    {
        return $this->status === Status::Error;
    }

    public function failed(Throwable|string $error): void
    {
        $previousStatus = $this->status;
        $this->status = Status::Failed;

        if ($error instanceof Throwable) {
            $this->error = SubscriptionError::fromThrowable($previousStatus, $error);

            return;
        }

        $this->error = new SubscriptionError($error, $previousStatus);
    }

    public function isFailed(): bool
    {
        return $this->status === Status::Failed;
    }

    public function retryAttempt(): int
    {
        return $this->retryAttempt;
    }

    public function doRetry(): void
    {
        if ($this->error === null) {
            throw new NoErrorToRetry();
        }

        $this->retryAttempt++;
        $this->status = $this->error->previousStatus;
        $this->error = null;
    }

    public function resetRetry(): void
    {
        $this->retryAttempt = 0;
    }

    public function lastSavedAt(): DateTimeImmutable|null
    {
        return $this->lastSavedAt;
    }

    public function updateLastSavedAt(DateTimeImmutable $lastSavedAt): void
    {
        $this->lastSavedAt = $lastSavedAt;
    }

    /** @param list<object>|null $cleanupTask */
    public function replaceCleanupTasks(array|null $cleanupTask): void
    {
        $this->cleanupTasks = $cleanupTask;
    }

    /** @return list<object>|null */
    public function cleanupTasks(): array|null
    {
        return $this->cleanupTasks;
    }

    public function hasCleanupTasks(): bool
    {
        return $this->cleanupTasks !== null;
    }
}
