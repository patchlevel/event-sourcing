<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\MicroAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\SharedApplyContext;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Integration\MicroAggregate\Events\ProfileCreated;

#[Aggregate('profile')]
#[Snapshot('default', 1)]
#[SharedApplyContext([PersonalInformation::class])]
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
