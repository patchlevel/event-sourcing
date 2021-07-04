<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;

/**
 * @extends SnapshotableAggregateRoot<array{id: string, email: string}>
 */
final class ProfileWithSnapshot extends SnapshotableAggregateRoot
{
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
        $self->apply(ProfileCreated::raise($id, $email));

        return $self;
    }

    public function publishMessage(Message $message): void
    {
        $this->apply(MessagePublished::raise(
            $this->id,
            $message,
        ));
    }

    public function visitProfile(ProfileId $profileId): void
    {
        $this->apply(ProfileVisited::raise($this->id, $profileId));
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

    protected function serialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'email' => $this->email->toString(),
        ];
    }

    protected static function deserialize(array $payload): SnapshotableAggregateRoot
    {
        $self = new self();
        $self->id = ProfileId::fromString($payload['id']);
        $self->email = Email::fromString($payload['email']);

        return $self;
    }
}
