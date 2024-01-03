<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;

#[Aggregate('profile_with_snapshot')]
#[Snapshot('memory', batch: 2, version: '1')]
#[SuppressMissingApply([ProfileVisited::class])]
final class ProfileWithSnapshot extends BasicAggregateRoot
{
    #[ProfileIdNormalizer]
    #[Id]
    private ProfileId $id;
    #[EmailNormalizer]
    private Email $email;
    /** @var array<Message> */
    #[ArrayNormalizer(new MessageNormalizer())]
    private array $messages;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    /** @return array<Message> */
    public function messages(): array
    {
        return $this->messages;
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
            $message,
        ));
    }

    public function visitProfile(ProfileId $profileId): void
    {
        $this->recordThat(new ProfileVisited($profileId));
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->email = $event->email;
        $this->messages = [];
    }

    #[Apply(MessagePublished::class)]
    protected function applyMessagePublished(MessagePublished $event): void
    {
        $this->messages[] = $event->message;
    }
}
