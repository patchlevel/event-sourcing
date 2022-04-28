<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use function array_key_exists;

final class InMemorySnapshotAdapter implements SnapshotAdapter
{
    /** @var array<string, array<string, mixed>> */
    private array $snapshots = [];

    public function save(string $key, array $data): void
    {
        $this->snapshots[$key] = $data;
    }

    public function load(string $key): array
    {
        if (!array_key_exists($key, $this->snapshots)) {
            throw new SnapshotNotFound($key);
        }

        return $this->snapshots[$key];
    }
}
