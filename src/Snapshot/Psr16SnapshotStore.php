<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Psr\SimpleCache\CacheInterface;

use function sprintf;

final class Psr16SnapshotStore implements SnapshotStore
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function save(Snapshot $snapshot): void
    {
        $this->cache->set(
            $this->key($snapshot->aggregate(), $snapshot->id()),
            [
                'playhead' => $snapshot->playhead(),
                'payload' => $snapshot->payload(),
            ]
        );
    }

    public function load(string $aggregate, string $id): Snapshot
    {
        /**
         * @var ?array{playhead: int, payload: array<string, mixed>} $data
         */
        $data = $this->cache->get(
            $this->key($aggregate, $id)
        );

        if ($data === null) {
            throw new SnapshotNotFound($aggregate, $id);
        }

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
