<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Store\NewStreamStartHeader;

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

        return $message->withHeader(new NewStreamStartHeader($metadata->splitStream));
    }
}
