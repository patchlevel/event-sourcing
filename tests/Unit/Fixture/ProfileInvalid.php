<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;

final class ProfileInvalid extends AggregateRoot
{
    private ProfileId $id;
    private Email $email;

    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->record(new ProfileCreated($id, $email));

        return $self;
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated1(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->email = $event->email;
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated2(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->email = $event->email;
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
