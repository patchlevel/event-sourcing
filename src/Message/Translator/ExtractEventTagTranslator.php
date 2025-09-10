<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\Serializer\EventTagExtractor;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

/** @experimental */
final class ExtractEventTagTranslator implements Translator
{
    public function __construct(
        private readonly EventTagExtractor $eventTagExtractor = new AttributeEventTagExtractor(),
        private readonly bool $skipAlreadyTagged = false,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        if ($this->skipAlreadyTagged && $message->hasHeader(TagsHeader::class)) {
            return [$message];
        }

        $tags = $this->eventTagExtractor->extract($message->event());

        return [$message->withHeader(new TagsHeader($tags))];
    }
}
