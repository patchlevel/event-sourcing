<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('message_published')]
final class MessagePublished
{
    public function __construct(
        public Message $message,
    ) {
    }
}
