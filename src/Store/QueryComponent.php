<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

use function array_diff;
use function sort;

/** @experimental */
final class QueryComponent
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly array $tags,
    ) {
        sort($tags);
    }

    public function match(Message $message): bool
    {
        if ($this->tags === []) {
            return true;
        }

        if (!$message->hasHeader(TagsHeader::class)) {
            return false;
        }

        return $this->isSubset($this->tags, $message->header(TagsHeader::class)->tags);
    }

    public function equals(self $queryComponent): bool
    {
        return $this->tags === $queryComponent->tags;
    }

    /**
     * @param list<string> $needle
     * @param list<string> $haystack
     */
    private function isSubset(array $needle, array $haystack): bool
    {
        return empty(array_diff($needle, $haystack));
    }
}
