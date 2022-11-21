<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;

final class ProjectorState
{
    public function __construct(
        private readonly ProjectorId $id,
        private ProjectorStatus $status = ProjectorStatus::Now,
        private int $position = 0
    ) {
    }

    public function id(): ProjectorId
    {
        return $this->id;
    }

    public function status(): ProjectorStatus
    {
        return $this->status;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function incrementPosition(): void
    {
        $this->position++;
    }

    public function isNew(): bool
    {
        return $this->status === ProjectorStatus::Now;
    }

    public function booting(): void
    {
        $this->status = ProjectorStatus::Booting;
    }

    public function isBooting(): bool
    {
        return $this->status === ProjectorStatus::Booting;
    }

    public function active(): void
    {
        $this->status = ProjectorStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status === ProjectorStatus::Active;
    }

    public function outdated(): void
    {
        $this->status = ProjectorStatus::Outdated;
    }

    public function isOutdated(): bool
    {
        return $this->status === ProjectorStatus::Outdated;
    }

    public function error(): void
    {
        $this->status = ProjectorStatus::Error;
    }

    public function isError(): bool
    {
        return $this->status === ProjectorStatus::Error;
    }
}
