<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{messageId: string}>
 */
final class MessageDeleted extends AggregateChanged
{
    public static function raise(
        ProfileId $profileId,
        MessageId $messageId
    ): static {
        return new static(
            $profileId->toString(),
            [
                'messageId' => $messageId->toString(),
            ]
        );
    }

    public function profileId(): ProfileId
    {
        return ProfileId::fromString($this->aggregateId);
    }

    public function messageId(): MessageId
    {
        return MessageId::fromString($this->payload['messageId']);
    }
}
