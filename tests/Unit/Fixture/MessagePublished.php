<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{message: array{id: string, text: string, createdAt: string}}>
 */
final class MessagePublished extends AggregateChanged
{
    public static function raise(Message $message): static
    {
        return new static(
            [
                'message' => $message->toArray(),
            ]
        );
    }

    public function message(): Message
    {
        return Message::fromArray($this->payload['message']);
    }
}
