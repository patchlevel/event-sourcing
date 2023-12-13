<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use function array_key_exists;

final class InMemorySnapshotAdapter implements SnapshotAdapter
{
    /** @var array<string, array<string, mixed>> */
    private array $snapshots = [];

    /** @param array<string, mixed> $data */
    public function save(string $key, array $data): void
    {
        $this->snapshots[$key] = $data;
    }

    /** @return array<string, mixed> */
    public function load(string $key): array
    {
        if (!array_key_exists($key, $this->snapshots)) {
            throw new SnapshotNotFound($key);
        }

        return $this->snapshots[$key];
    }

    public function clear(): void
    {
        $this->snapshots = [];
    }
}
