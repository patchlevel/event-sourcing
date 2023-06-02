<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('message_deleted')]
final class MessageDeleted
{
    public function __construct(
        public MessageId $messageId,
    ) {
    }
}
