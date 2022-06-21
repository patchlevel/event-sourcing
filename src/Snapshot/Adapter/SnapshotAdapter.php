<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

interface SnapshotAdapter
{
    /**
     * @param array<string, mixed> $data
     */
    public function save(string $key, array $data): void;

    /**
     * @return array<string, mixed>
     *
     * @throws SnapshotNotFound
     */
    public function load(string $key): array;
}
