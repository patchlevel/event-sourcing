<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\NonStrictApplyMethod;

final class ProfileWithNonStrictApply extends AggregateRoot
{
    use NonStrictApplyMethod;

    private ProfileId $id;
    private Email $email;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $email));

        return $self;
    }

    public function publishMessage(Message $message): void
    {
        $this->record(MessagePublished::raise(
            $this->id,
            $message,
        ));
    }

    public function visitProfile(ProfileId $profileId): void
    {
        $this->record(ProfileVisited::raise($this->id, $profileId));
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
        $this->email = $event->email();
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
