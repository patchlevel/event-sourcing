<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use function array_key_exists;

final class InMemorySnapshotAdapter implements SnapshotAdapter
{
    /** @var array<string, array{int, array<string, mixed>}> */
    private array $snapshots = [];

    public function save(string $key, int $playhead, array $payload): void
    {
        $this->snapshots[$key] = [$playhead, $payload];
    }

    public function load(string $key): array
    {
        if (!array_key_exists($key, $this->snapshots)) {
            throw new SnapshotNotFound($key);
        }

        return $this->snapshots[$key];
    }
}
