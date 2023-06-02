<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Snapshot
{
    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param array<string, mixed>        $payload
     */
    public function __construct(
        private readonly string $aggregate,
        private readonly string $id,
        private readonly int $playhead,
        private readonly array $payload,
    ) {
    }

    /** @return class-string<AggregateRoot> */
    public function aggregate(): string
    {
        return $this->aggregate;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function playhead(): int
    {
        return $this->playhead;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
