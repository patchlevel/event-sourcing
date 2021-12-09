<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Psr\Cache\CacheItemPoolInterface;

use function sprintf;

final class Psr6SnapshotStore implements SnapshotStore
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function save(Snapshot $snapshot): void
    {
        $key = $this->key($snapshot->aggregate(), $snapshot->id());
        $item = $this->cache->getItem($key);

        $item->set([
            'playhead' => $snapshot->playhead(),
            'payload' => $snapshot->payload(),
        ]);

        $this->cache->save($item);
    }

    public function load(string $aggregate, string $id): Snapshot
    {
        $key = $this->key($aggregate, $id);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            throw new SnapshotNotFound($aggregate, $id);
        }

        /**
         * @var array{playhead: int, payload: array<string, mixed>} $data
         */
        $data = $item->get();

        return new Snapshot(
            $aggregate,
            $id,
            $data['playhead'],
            $data['payload']
        );
    }

    private function key(string $aggregate, string $id): string
    {
        return sprintf('%s-%s', $aggregate, $id);
    }
}
