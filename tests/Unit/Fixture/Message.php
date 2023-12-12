<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use DateTimeImmutable;

use const DATE_ATOM;

final class Message
{
    private function __construct(private MessageId $messageId, private string $text, private DateTimeImmutable $createdAt)
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

    /** @return array{id: string, text: string, createdAt: string} */
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
        return new self($messageId, $text, new DateTimeImmutable());
    }

    /** @param array{id: string, text: string, createdAt: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(MessageId::fromString($data['id']), $data['text'], new DateTimeImmutable($data['createdAt']));
    }
}
