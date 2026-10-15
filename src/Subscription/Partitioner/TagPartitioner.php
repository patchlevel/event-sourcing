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
 * The tag with the given prefix is the key, e.g. `new TagPartitioner('course:')` for the tags of the dynamic
 * consistency boundary. A message needs exactly one tag with the prefix.
 */
final class TagPartitioner implements Partitioner
{
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function __invoke(Message $message): string
    {
        try {
            $tags = $message->header(TagsHeader::class)->tags;
        } catch (HeaderNotFound) {
            throw TagNotUnique::missing($this->prefix);
        }

        $matching = array_values(array_filter(
            $tags,
            fn (string $tag): bool => str_starts_with($tag, $this->prefix),
        ));

        if ($matching === []) {
            throw TagNotUnique::missing($this->prefix);
        }

        if (count($matching) > 1) {
            throw TagNotUnique::ambiguous($this->prefix, $matching);
        }

        return $matching[0];
    }
}
