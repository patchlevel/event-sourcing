<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\Projector\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;

final class ProjectorData
{
    public function __construct(
        private readonly ProjectorId $id,
        private ProjectorStatus $status = ProjectorStatus::Pending,
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

    public function stale(): void
    {
        $this->status = ProjectorStatus::Stale;
    }

    public function running(): void
    {
        $this->status = ProjectorStatus::Running;
    }
}
