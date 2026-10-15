<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

use function array_filter;
use function array_values;
use function count;
use function str_starts_with;

/**
 * The tag with the given prefix is the key, e.g. `new TagPartitioner('course')` for the `course:{id}` tags of
 * `#[EventTag(prefix: 'course')]`. A message needs exactly one tag with the prefix.
 */
final class TagPartitioner implements Partitioner
{
    /** @param non-empty-string $prefix */
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function __invoke(Message $message): string
    {
        $prefix = $this->prefix . ':';

        try {
            $tags = $message->header(TagsHeader::class)->tags;
        } catch (HeaderNotFound) {
            throw TagNotUnique::missing($prefix);
        }

        $matching = array_values(array_filter(
            $tags,
            static fn (string $tag): bool => str_starts_with($tag, $prefix),
        ));

        if ($matching === []) {
            throw TagNotUnique::missing($prefix);
        }

        if (count($matching) > 1) {
            throw TagNotUnique::ambiguous($prefix, $matching);
        }

        return $matching[0];
    }
}
