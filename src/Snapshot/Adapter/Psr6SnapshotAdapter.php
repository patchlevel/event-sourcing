<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use Psr\Cache\CacheItemPoolInterface;

final class Psr6SnapshotAdapter implements SnapshotAdapter
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function save(string $key, int $playhead, array $payload): void
    {
        $item = $this->cache->getItem($key);
        $item->set([$playhead, $payload]);
        $this->cache->save($item);
    }

    public function load(string $key): array
    {
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            throw new SnapshotNotFound($key);
        }

        /**
         * @var array{int, array<string, mixed>} $data
         */
        $data = $item->get();

        return $data;
    }
}
