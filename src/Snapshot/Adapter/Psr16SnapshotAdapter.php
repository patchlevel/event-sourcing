<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use Psr\SimpleCache\CacheInterface;

final class Psr16SnapshotAdapter implements SnapshotAdapter
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function save(string $key, array $data): void
    {
        $this->cache->set($key, $data);
    }

    public function load(string $key): array
    {
        /**
         * @var ?array<string, mixed> $data
         */
        $data = $this->cache->get($key);

        if ($data === null) {
            throw new SnapshotNotFound($key);
        }

        return $data;
    }
}
