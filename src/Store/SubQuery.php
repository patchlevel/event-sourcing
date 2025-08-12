<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

use function array_diff;
use function in_array;
use function sort;

/** @experimental */
final class SubQuery
{
    /**
     * @param list<string>       $tags
     * @param list<class-string> $events
     */
    public function __construct(
        public readonly array $tags = [],
        public readonly array $events = [],
        public readonly string|null $streamName = null,
    ) {
        sort($tags);
        sort($events);
    }

    public function match(Message $message): bool
    {
        if ($this->tags === [] && $this->events === []) {
            return true;
        }

        if (!$message->hasHeader(TagsHeader::class)) {
            return false;
        }

        if ($this->streamName !== null && $message->header(StreamNameHeader::class)->streamName !== $this->streamName) {
            return false;
        }

        if ($this->events !== [] && !in_array($message->event()::class, $this->events, true)) {
            return false;
        }

        return $this->isSubset($this->tags, $message->header(TagsHeader::class)->tags);
    }

    public function equals(self $queryComponent): bool
    {
        return $this->streamName === $queryComponent->streamName
            && $this->tags === $queryComponent->tags
            && $this->events === $queryComponent->events;
    }

    /**
     * @param list<string> $needle
     * @param list<string> $haystack
     */
    private function isSubset(array $needle, array $haystack): bool
    {
        return empty(array_diff($needle, $haystack));
    }

    public function empty(): bool
    {
        return $this->streamName === null && $this->tags === [] && $this->events === [];
    }
}
