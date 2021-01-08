<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use DateTimeImmutable;

use const DATE_ATOM;

final class Message
{
    private MessageId $messageId;
    private string $text;
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public function id(): MessageId
    {
        return $this->messageId;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->messageId->toString(),
            'text' => $this->text,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    public static function create(MessageId $messageId, string $text): self
    {
        $self = new self();
        $self->messageId = $messageId;
        $self->text = $text;
        $self->createdAt = new DateTimeImmutable();

        return $self;
    }

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->messageId = MessageId::fromString($data['id']);
        $self->text = $data['text'];
        $self->createdAt = new DateTimeImmutable($data['createdAt']);

        return $self;
    }
}
