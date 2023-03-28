<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[Aggregate('profile')]
#[SuppressMissingApply([MessageDeleted::class])]
final class Profile extends BasicAggregateRoot
{
    private ProfileId $id;
    private Email $email;
    private int $visits = 0;

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
        return $this->visits;
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

    public function splitIt(): void
    {
        $this->recordThat(new SplittingEvent($this->email, $this->visits));
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

        $this->visits++;
    }

    #[Apply(NameChanged::class)]
    protected function applyNameChanged(NameChanged|ProfileVisited $event): void
    {
    }

    #[Apply(SplittingEvent::class)]
    protected function applySplittingEvent(SplittingEvent $event): void
    {
        $this->email = $event->email;
        $this->visits = $event->visits;
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
