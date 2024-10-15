<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;

interface AdapterRepository
{
    public function get(string $name): SnapshotAdapter;
}
