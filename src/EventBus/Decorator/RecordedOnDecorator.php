<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Decorator;

use Patchlevel\EventSourcing\EventBus\Message;
use Psr\Clock\ClockInterface;

final class RecordedOnDecorator implements MessageDecorator
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {
    }

    public function __invoke(Message $message): Message
    {
        return $message->withRecordedOn($this->clock->now());
    }
}
