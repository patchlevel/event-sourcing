<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile_with_broken_playhead')]
final class ProfileWithBrokenPlayhead extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    public function visit(): void
    {
        $this->recordThat(new ProfileVisited($this->id));
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
    }

    #[Apply]
    protected function applyProfileVisited(ProfileVisited $event): void
    {
    }

    public function playhead(): int
    {
        return parent::playhead() - 2;
    }
}
