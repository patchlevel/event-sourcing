<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\Reborn;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Normalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

#[Aggregate('profile')]
#[Snapshot('default')]
final class Profile extends BasicAggregateRoot
{
    #[ProfileIdNormalizer]
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

    public function reborn(): void
    {
        $this->recordThat(new Reborn(
            $this->id,
            $this->name,
        ));
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
    }

    #[Apply]
    protected function applyReborn(Reborn $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
