<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Decorator;

use Patchlevel\EventSourcing\Clock\Clock;
use Patchlevel\EventSourcing\EventBus\Message;

final class RecordedOnDecorator implements MessageDecorator
{
    public function __construct(private readonly Clock $clock)
    {
    }

    public function __invoke(Message $message): Message
    {
        return $message->withRecordedOn($this->clock->create());
    }
}
