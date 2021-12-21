<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\NonStrictApplyMethod;
use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;

final class ProfileWithSnapshot extends SnapshotableAggregateRoot
{
    use NonStrictApplyMethod;

    private ProfileId $id;
    private Email $email;
    /** @var array<Message> */
    private array $messages;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    /**
     * @return array<Message>
     */
    public function messages(): array
    {
        return $this->messages;
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
        $this->messages = [];
    }

    protected function applyMessagePublished(MessagePublished $event): void
    {
        $this->messages[] = $event->message();
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }

    /**
     * @return array{id: string, email: string}
     */
    protected function serialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'email' => $this->email->toString(),
        ];
    }

    /**
     * @param array{id: string, email: string} $payload
     */
    protected static function deserialize(array $payload): static
    {
        $self = new static();
        $self->id = ProfileId::fromString($payload['id']);
        $self->email = Email::fromString($payload['email']);

        return $self;
    }
}
