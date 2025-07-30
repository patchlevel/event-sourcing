<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

use function array_diff;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function sort;

/**
 * @experimental
 * @extends Projection<array<string, mixed>>
 */
final class CompositeProjection
{
    /** @param array<string, Projection> $projections */
    public function __construct(
        private readonly array $projections,
    ) {
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        $tags = [];

        foreach ($this->projections as $projection) {
            $tags = array_merge($tags, $projection->tagFilter());
        }

        return array_values(array_unique($tags));
    }

    /** @return list<list<string>> */
    public function groupedTagFilter(): array
    {
        $result = [];

        foreach ($this->projections as $projection) {
            $tags = $projection->tagFilter();

            sort($tags);

            if (in_array($tags, $result, true)) {
                continue;
            }

            $result[] = $tags;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function initialState(): array
    {
        return array_map(static function (Projection $projection) {
            return $projection->initialState();
        }, $this->projections);
    }

    public function apply(mixed $state, Message $message): mixed
    {
        $tags = $message->header(TagsHeader::class)->tags;

        foreach ($this->projections as $name => $projection) {
            $neededTags = $projection->tagFilter();

            if (!$this->isSubset($neededTags, $tags)) {
                continue;
            }

            $state[$name] = $projection->apply($state[$name], $message);
        }

        return $state;
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
