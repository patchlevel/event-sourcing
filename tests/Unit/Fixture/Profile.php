<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[Aggregate('profile')]
#[SuppressMissingApply([MessageDeleted::class])]
final class Profile extends AggregateRoot
{
    private ProfileId $id;
    private Email $email;
    private int $visited = 0;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function visited(): int
    {
        return $this->visited;
    }

    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $email));

        return $self;
    }

    public function publishMessage(Message $message): void
    {
        $this->recordThat(new MessagePublished(
            $message
        ));
    }

    public function deleteMessage(MessageId $messageId): void
    {
        $this->recordThat(new MessageDeleted(
            $messageId
        ));
    }

    public function visitProfile(ProfileId $profileId): void
    {
        $this->recordThat(new ProfileVisited($profileId));
    }

    #[Apply(ProfileCreated::class)]
    #[Apply(ProfileVisited::class)]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
        if ($event instanceof ProfileCreated) {
            $this->id = $event->profileId;
            $this->email = $event->email;

            return;
        }

        $this->visited++;
    }

    #[Apply(NameChanged::class)]
    protected function applyNameChanged(NameChanged|ProfileVisited $event): void
    {
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
