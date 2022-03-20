<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class MessageDeleted
{
    public function __construct(
        public MessageId $messageId
    ) {
    }
}
