<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

use function hash;

/**
 * PSR-6 and PSR-16 reserve the characters {}()/\@: and only guarantee keys up to 64 characters,
 * so class names are hashed instead of being used as they are.
 * The prefix keeps the metadata types apart if they share the same cache.
 *
 * @internal
 */
final class CacheKey
{
    /** @param class-string $class */
    public static function forAggregateRoot(string $class): string
    {
        return 'aggregate_root_metadata_' . hash('xxh128', $class);
    }

    /** @param class-string $class */
    public static function forEvent(string $class): string
    {
        return 'event_metadata_' . hash('xxh128', $class);
    }

    /** @param class-string $class */
    public static function forSubscriber(string $class): string
    {
        return 'subscriber_metadata_' . hash('xxh128', $class);
    }
}
