<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\DCB\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\DCB\EventTagExtractor;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

final class EventTagDecorator implements MessageDecorator
{
    public function __construct(
        private readonly EventTagExtractor $eventTagExtractor = new AttributeEventTagExtractor(),
    ) {
    }

    public function __invoke(Message $message): Message
    {
        $tags = $this->eventTagExtractor->extract($message->event());

        return $message->withHeader(new TagsHeader($tags));
    }
}
