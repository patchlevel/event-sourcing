<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Snapshot\Snapshot;

/**
 * @template-covariant T of array<string, mixed>
 */
abstract class SnapshotableAggregateRoot extends AggregateRoot
{
    /**
     * @return T
     */
    abstract protected function serialize(): array;

    /**
     * @param T $payload
     *
     * @return static<T>
     */
    abstract protected static function deserialize(array $payload): self;

    /**
     * @param array<AggregateChanged> $stream
     *
     * @return static<T>
     */
    public static function createFromSnapshot(Snapshot $snapshot, array $stream): self
    {
        $self = static::deserialize($snapshot->payload());
        $self->playhead = $snapshot->playhead();

        foreach ($stream as $message) {
            $self->playhead++;
            $self->handle($message);
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
