<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

final class Projection
{
    public function __construct(
        private readonly ProjectionId $id,
        private ProjectionStatus $status = ProjectionStatus::New,
        private int $position = 0
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

    public function incrementPosition(): void
    {
        $this->position++;
    }

    public function isNew(): bool
    {
        return $this->status === ProjectionStatus::New;
    }

    public function booting(): void
    {
        $this->status = ProjectionStatus::Booting;
    }

    public function isBooting(): bool
    {
        return $this->status === ProjectionStatus::Booting;
    }

    public function active(): void
    {
        $this->status = ProjectionStatus::Active;
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

    public function error(): void
    {
        $this->status = ProjectionStatus::Error;
    }

    public function isError(): bool
    {
        return $this->status === ProjectionStatus::Error;
    }
}
