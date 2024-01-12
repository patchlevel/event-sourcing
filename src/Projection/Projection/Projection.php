<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

final class Projection
{
    public function __construct(
        private readonly ProjectionId $id,
        private ProjectionStatus $status = ProjectionStatus::New,
        private int $position = 0,
        private ProjectionError|null $error = null,
        private int $retry = 0,
    ) {
    }

    public function id(): ProjectionId
    {
        return $this->id;
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

    public function outdated(): void
    {
        $this->status = ProjectionStatus::Outdated;
    }

    public function isOutdated(): bool
    {
        return $this->status === ProjectionStatus::Outdated;
    }

    public function error(ProjectionError|null $error = null): void
    {
        $this->status = ProjectionStatus::Error;
        $this->error = $error;
    }

    public function isError(): bool
    {
        return $this->status === ProjectionStatus::Error;
    }

    public function incrementRetry(): void
    {
        $this->retry++;
    }

    public function retry(): int
    {
        return $this->retry;
    }

    public function resetRetry(): void
    {
        $this->retry = 0;
    }

    public function disallowRetry(): void
    {
        $this->retry = -1;
    }

    public function isRetryDisallowed(): bool
    {
        return $this->retry === -1;
    }
}
