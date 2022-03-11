<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{messageId: string}>
 */
final class MessageDeleted extends AggregateChanged
{
    public static function raise(MessageId $messageId): static
    {
        return new static(
            [
                'messageId' => $messageId->toString(),
            ]
        );
    }

    public function messageId(): MessageId
    {
        return MessageId::fromString($this->payload['messageId']);
    }
}
