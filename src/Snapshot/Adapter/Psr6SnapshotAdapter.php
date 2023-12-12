<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use Psr\Cache\CacheItemPoolInterface;

final class Psr6SnapshotAdapter implements SnapshotAdapter
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    /** @param array<string, mixed> $data */
    public function save(string $key, array $data): void
    {
        $item = $this->cache->getItem($key);
        $item->set($data);
        $this->cache->save($item);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SnapshotNotFound
     */
    public function load(string $key): array
    {
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            throw new SnapshotNotFound($key);
        }

        /** @var array<string, mixed> $data */
        $data = $item->get();

        return $data;
    }
}
