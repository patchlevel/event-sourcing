<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;

use function array_key_exists;

final class ArrayAdapterRepository implements AdapterRepository
{
    /** @param array<string, SnapshotAdapter> $snapshotAdapters */
    public function __construct(
        private readonly array $snapshotAdapters,
    ) {
    }

    public function get(string $name): SnapshotAdapter
    {
        if (!array_key_exists($name, $this->snapshotAdapters)) {
            throw new AdapterNotFound($name);
        }

        return $this->snapshotAdapters[$name];
    }
}
