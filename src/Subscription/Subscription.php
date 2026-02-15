<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

use DateTimeImmutable;
use Throwable;

final class Subscription
{
    public const DEFAULT_GROUP = 'default';

    /** @param list<object>|null $cleanupTasks */
    public function __construct(
        private readonly string $id,
        private string $group = self::DEFAULT_GROUP,
        private RunMode $runMode = RunMode::FromBeginning,
        private Status $status = Status::New,
        private int $position = 0,
        private SubscriptionError|null $error = null,
        private int $retryAttempt = 0,
        private DateTimeImmutable|null $lastSavedAt = null,
        private array|null $cleanupTasks = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
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

    public function position(): int
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
