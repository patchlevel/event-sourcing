<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

interface SnapshotAdapter
{
    /**
     * @param array<string, mixed> $payload
     */
    public function save(string $key, int $playhead, array $payload): void;

    /**
     * @return array{int, array<string, mixed>}
     *
     * @throws SnapshotNotFound
     */
    public function load(string $key): array;
}
