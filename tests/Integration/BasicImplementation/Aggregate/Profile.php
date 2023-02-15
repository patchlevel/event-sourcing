<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Normalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;

#[Aggregate('profile')]
#[Snapshot('default', 100)]
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

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
