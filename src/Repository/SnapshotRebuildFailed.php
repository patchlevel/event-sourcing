<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Throwable;

use function sprintf;

class SnapshotRebuildFailed extends RepositoryException
{
    private Snapshot $snapshot;

    public function __construct(Snapshot $snapshot, Throwable $previous)
    {
        parent::__construct(
            sprintf(
                'Rebuild from snapshot of aggregate "%s" with the id "%s" failed',
                $snapshot->aggregate(),
                $snapshot->id()
            ),
            0,
            $previous
        );

        $this->snapshot = $snapshot;
    }

    public function snapshot(): Snapshot
    {
        return $this->snapshot;
    }
}
