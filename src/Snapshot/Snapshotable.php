<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

interface Snapshotable
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(): array;

    /**
     * @param array<string, mixed> $payload
     * @return static
     */
    public static function unserialize(int $playhead, array $payload): self;
}
