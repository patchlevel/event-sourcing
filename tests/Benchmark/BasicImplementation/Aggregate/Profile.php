<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

final class Profile extends SnapshotableAggregateRoot
{
    private ProfileId $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }

    public static function create(ProfileId $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name));

        return $self;
    }

    public function changeName(string $name): void
    {
        $this->recordThat(new NameChanged($name));
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
            'id' => $this->id->toString(),
            'name' => $this->name,
        ];
    }

    /**
     * @param array{id: string, name: string} $payload
     */
    protected static function deserialize(array $payload): static
    {
        $self = new static();
        $self->id = ProfileId::fromString($payload['id']);
        $self->name = $payload['name'];

        return $self;
    }

    public function name(): string
    {
        return $this->name;
    }
}
