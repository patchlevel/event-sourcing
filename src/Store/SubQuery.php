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
        if ($this->streamName !== null && $this->streamName !== $other->streamName) {
            return false;
        }

        if (!self::isSubset($this->tags, $other->tags)) {
            return false;
        }

        if (!self::isSubset($this->events, $other->events)) {
            return false;
        }

        return !$this->onlyLastEvent || $other->onlyLastEvent;
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
