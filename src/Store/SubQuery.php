<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

use function array_diff;
use function in_array;

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
        public readonly bool $onlyLastEvent = false,
    ) {
    }

    public function match(Message $message): bool
    {
        if (
            $this->streamName !== null
            && (!$message->hasHeader(StreamNameHeader::class)
                || $message->header(StreamNameHeader::class)->streamName !== $this->streamName)
        ) {
            return false;
        }

        if (
            $this->tags !== []
            && (!$message->hasHeader(TagsHeader::class)
                || !self::isSubset($this->tags, $message->header(TagsHeader::class)->tags))
        ) {
            return false;
        }

        return $this->events === [] || in_array($message->event()::class, $this->events, true);
    }

    public function empty(): bool
    {
        return $this->streamName === null && $this->tags === [] && $this->events === [];
    }

    public function includes(SubQuery $other): bool
    {
        // Only the last matching event is loaded. That event is only the last event
        // of the other query as well, if both queries match exactly the same events.
        if ($this->onlyLastEvent) {
            return $other->onlyLastEvent
                && $this->streamName === $other->streamName
                && self::isSameSet($this->tags, $other->tags)
                && self::isSameSet($this->events, $other->events);
        }

        if ($this->streamName !== null && $this->streamName !== $other->streamName) {
            return false;
        }

        if (!self::isSubset($this->tags, $other->tags)) {
            return false;
        }

        // events is an allow list: an empty list matches everything, so it is the
        // broadest filter. Otherwise this query only covers the other one when
        // every event the other query allows is also allowed here.
        return $this->events === [] || ($other->events !== [] && self::isSubset($other->events, $this->events));
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function isSameSet(array $a, array $b): bool
    {
        return self::isSubset($a, $b) && self::isSubset($b, $a);
    }

    /**
     * @param list<string> $subject
     * @param list<string> $off
     */
    private static function isSubset(array $subject, array $off): bool
    {
        return empty(array_diff($subject, $off));
    }
}
