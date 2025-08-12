<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

use function array_map;

/** @experimental */
final class StoreEventAppender implements EventAppender
{
    public function __construct(
        private readonly AppendStore $store,
        private readonly EventTagExtractor $eventTagExtractor = new AttributeEventTagExtractor(),
        private readonly string $defaultStreamName = 'main',
    ) {
    }

    /** @param iterable<object> $events */
    public function append(
        iterable $events,
        AppendCondition|null $appendCondition = null,
        string|null $streamName = null,
    ): void {
        $messages = array_map(
            fn (object $event) => Message::create($event)
                ->withHeader(new StreamNameHeader($streamName ?? $this->defaultStreamName))
                ->withHeader(new TagsHeader($this->eventTagExtractor->extract($event))),
            $events,
        );

        $this->store->append($messages, $appendCondition);
    }
}
