<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;

final class ProjectorState
{
    public function __construct(
        private readonly ProjectorId $id,
        private ProjectorStatus $status = ProjectorStatus::Booting,
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

    public function error(): void
    {
        $this->status = ProjectorStatus::Error;
    }

    public function outdated(): void
    {
        $this->status = ProjectorStatus::Outdated;
    }

    public function active(): void
    {
        $this->status = ProjectorStatus::Active;
    }
}
