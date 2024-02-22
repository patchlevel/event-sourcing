<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use DateTimeImmutable;
use Throwable;

final class Projection
{
    public const DEFAULT_GROUP = 'default';

    public function __construct(
        private readonly string $id,
        private readonly string $group = self::DEFAULT_GROUP,
        private readonly RunMode $runMode = RunMode::FromBeginning,
        private ProjectionStatus $status = ProjectionStatus::New,
        private int $position = 0,
        private ProjectionError|null $error = null,
        private int $retryAttempt = 0,
        private DateTimeImmutable|null $lastSavedAt = null,
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

    public function runMode(): RunMode
    {
        return $this->runMode;
    }

    public function status(): ProjectionStatus
    {
        return $this->status;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function projectionError(): ProjectionError|null
    {
        return $this->error;
    }

    public function changePosition(int $position): void
    {
        $this->position = $position;
    }

    public function new(): void
    {
        $this->status = ProjectionStatus::New;
        $this->error = null;
    }

    public function isNew(): bool
    {
        return $this->status === ProjectionStatus::New;
    }

    public function booting(): void
    {
        $this->status = ProjectionStatus::Booting;
        $this->error = null;
    }

    public function isBooting(): bool
    {
        return $this->status === ProjectionStatus::Booting;
    }

    public function active(): void
    {
        $this->status = ProjectionStatus::Active;
        $this->error = null;
    }

    public function isActive(): bool
    {
        return $this->status === ProjectionStatus::Active;
    }

    public function finished(): void
    {
        $this->status = ProjectionStatus::Finished;
        $this->error = null;
    }

    public function isFinished(): bool
    {
        return $this->status === ProjectionStatus::Finished;
    }

    public function outdated(): void
    {
        $this->status = ProjectionStatus::Outdated;
    }

    public function isOutdated(): bool
    {
        return $this->status === ProjectionStatus::Outdated;
    }

    public function error(Throwable|string $error): void
    {
        $previousStatus = $this->status;
        $this->status = ProjectionStatus::Error;

        if ($error instanceof Throwable) {
            $this->error = ProjectionError::fromThrowable($previousStatus, $error);

            return;
        }

        $this->error = new ProjectionError($error, $previousStatus);
    }

    public function isError(): bool
    {
        return $this->status === ProjectionStatus::Error;
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
}
