<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Snapshot\Snapshot;

abstract class SnapshotableAggregateRoot extends AggregateRoot
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function serialize(): array;

    /**
     * @param array<string, mixed> $payload
     */
    abstract protected static function deserialize(array $payload): static;

    /**
     * @param list<Message> $stream
     */
    final public static function createFromSnapshot(Snapshot $snapshot, array $stream): static
    {
        $self = static::deserialize($snapshot->payload());
        $self->playhead = $snapshot->playhead();

        foreach ($stream as $message) {
            $self->playhead++;

            if ($self->playhead !== $message->playhead()) {
                throw new PlayheadSequenceMismatch();
            }

            $self->apply($message->event());
        }

        return $self;
    }

    final public function toSnapshot(): Snapshot
    {
        return new Snapshot(
            static::class,
            $this->aggregateRootId(),
            $this->playhead,
            $this->serialize()
        );
    }
}
