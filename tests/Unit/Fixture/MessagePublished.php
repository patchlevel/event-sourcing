<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class MessagePublished
{
    public function __construct(
        public Message $message
    ) {
    }
}
