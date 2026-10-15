<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Partitioner;

use RuntimeException;

use function implode;
use function sprintf;

final class TagNotUnique extends RuntimeException
{
    public static function missing(string $prefix): self
    {
        return new self(sprintf('The message has no tag with the prefix "%s" to partition by.', $prefix));
    }

    /** @param list<string> $tags */
    public static function ambiguous(string $prefix, array $tags): self
    {
        return new self(sprintf(
            'The message has several tags with the prefix "%s" to partition by: %s.',
            $prefix,
            implode(', ', $tags),
        ));
    }
}
