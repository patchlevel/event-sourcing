<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use Psr\SimpleCache\CacheInterface;

final class Psr16SnapshotAdapter implements SnapshotAdapter
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function save(string $key, array $data): void
    {
        $this->cache->set($key, $data);
    }

    /** @return array<string, mixed> */
    public function load(string $key): array
    {
        /** @var ?array<string, mixed> $data */
        $data = $this->cache->get($key);

        if ($data === null) {
            throw new SnapshotNotFound($key);
        }

        return $data;
    }
}
