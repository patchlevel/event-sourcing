<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Snapshot\Snapshot;

abstract class SnapshotableAggregateRoot extends AggregateRoot
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function serialize(): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return static
     */
    abstract protected static function deserialize(array $payload): self;

    /**
     * @param array<AggregateChanged<array<string, mixed>>> $stream
     *
     * @return static
     */
    public static function createFromSnapshot(Snapshot $snapshot, array $stream): self
    {
        $self = static::deserialize($snapshot->payload());
        $self->playhead = $snapshot->playhead();

        foreach ($stream as $message) {
            $self->playhead++;
            $self->apply($message);
        }

        return $self;
    }

    public function toSnapshot(): Snapshot
    {
        return new Snapshot(
            static::class,
            $this->aggregateRootId(),
            $this->playhead,
            $this->serialize()
        );
    }
}
