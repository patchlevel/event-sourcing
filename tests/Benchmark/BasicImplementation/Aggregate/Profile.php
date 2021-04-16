<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

final class Profile extends SnapshotableAggregateRoot
{
    private string $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }

    public static function create(string $id, string $name): self
    {
        $self = new self();
        $self->apply(ProfileCreated::raise($id, $name));

        return $self;
    }

    public function changeName(string $name): void
    {
        $this->apply(NameChanged::raise($this->id, $name));
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
        $this->name = $event->name();
    }

    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name();
    }

    /**
     * @return array{id: string, name: string}
     */
    protected function serialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    /**
     * @param array{id: string, name: string} $payload
     */
    protected static function deserialize(array $payload): self
    {
        $self = new self();
        $self->id = $payload['id'];
        $self->name = $payload['name'];

        return $self;
    }
}
