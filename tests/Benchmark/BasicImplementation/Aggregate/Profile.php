<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

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
        $self->record(new ProfileCreated($id, $name));

        return $self;
    }

    public function changeName(string $name): void
    {
        $this->record(new NameChanged($name));
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    #[Apply(NameChanged::class)]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
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
    protected static function deserialize(array $payload): static
    {
        $self = new static();
        $self->id = $payload['id'];
        $self->name = $payload['name'];

        return $self;
    }

    public function name(): string
    {
        return $this->name;
    }
}
