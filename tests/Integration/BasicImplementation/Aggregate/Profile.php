<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

/**
 * @template T of array{id: string}
 * @template-extends SnapshotableAggregateRoot<T>
 */
final class Profile extends SnapshotableAggregateRoot
{
    private string $id;

    public function aggregateRootId(): string
    {
        return $this->id;
    }

    public static function create(string $id): self
    {
        $self = new self();
        $self->apply(ProfileCreated::raise($id));

        return $self;
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
    }

    protected function serialize(): array
    {
        return [
            'id' => $this->id,
        ];
    }

    protected static function deserialize(array $payload): self
    {
        $self = new self();
        $self->id = $payload['id'];

        return $self;
    }
}
