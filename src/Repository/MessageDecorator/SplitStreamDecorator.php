<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Store\StreamStartHeader;

final class SplitStreamDecorator implements MessageDecorator
{
    public function __construct(
        private readonly EventMetadataFactory $eventMetadataFactory,
    ) {
    }

    public function __invoke(Message $message): Message
    {
        $event = $message->event();
        $metadata = $this->eventMetadataFactory->metadata($event::class);

        if (!$metadata->splitStream) {
            return $message;
        }

        return $message->withHeader(new StreamStartHeader());
    }
}
