<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events\ProfileCreated;

#[Aggregate('profile')]
#[Snapshot('default', 1)]
#[SuppressMissingApply([NameChanged::class])]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

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
    }
}
