<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\NonStrictApplyMethod;
use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

final class Profile extends SnapshotableAggregateRoot
{
    use NonStrictApplyMethod;

    private string $id;

    public function aggregateRootId(): string
    {
        return $this->id;
    }

    public static function create(string $id): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id));

        return $self;
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
    }

    /**
     * @return array{id: string}
     */
    protected function serialize(): array
    {
        return [
            'id' => $this->id,
        ];
    }

    /**
     * @param array{id: string} $payload
     */
    protected static function deserialize(array $payload): self
    {
        $self = new self();
        $self->id = $payload['id'];

        return $self;
    }
}
