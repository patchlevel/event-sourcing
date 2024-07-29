<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store\Events;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('extern')]
final class ExternEvent
{
    public function __construct(
        public string $message,
    ) {
    }
}
