<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

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
        private int $retry = 0,
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
