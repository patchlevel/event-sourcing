<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Store\SubQuery;

use function array_map;
use function array_unique;
use function array_values;

/**
 * Stores aggregates without streams: every event lands in the same global log
 * and belongs to its aggregate only through the tag "{aggregate name}:{aggregate id}".
 * This way aggregates and decision models can share one event log.
 *
 * The version is the index of the last event with this tag,
 * concurrency is guaranteed by an append condition on the tag.
 *
 * @experimental
 */
final class TagStoreAdapter implements StoreAdapter
{
    public function __construct(
        private readonly AppendStore $store,
    ) {
    }

    /**
     * @param AggregateRootMetadata<*> $metadata
     * @param int<0, max>|null         $fromPlayhead
     */
    public function load(AggregateRootMetadata $metadata, string $aggregateId, int|null $fromPlayhead = null): LoadedStream
    {
        $messages = [];
        $playhead = 0;
        $startPlayhead = $fromPlayhead ?? 0;
        $index = 0;

        foreach ($this->store->query($this->query($metadata, $aggregateId)) as $message) {
            $playhead++;
            $index = $message->header(IndexHeader::class)->index;

            // A split stream starts over, everything before the start is not needed anymore.
            if ($fromPlayhead === null && $message->hasHeader(StreamStartHeader::class)) {
                $messages = [];
                $startPlayhead = $playhead - 1;
            }

            if ($fromPlayhead !== null && $playhead <= $fromPlayhead) {
                continue;
            }

            $messages[] = $message;
        }

        $version = new Version($index);

        return new LoadedStream(
            new Stream($messages),
            $startPlayhead,
            static fn () => $version,
        );
    }

    /** @param AggregateRootMetadata<*> $metadata */
    public function has(AggregateRootMetadata $metadata, string $aggregateId): bool
    {
        $stream = $this->store->query($this->query($metadata, $aggregateId, true));

        try {
            return $stream->current() !== null;
        } finally {
            $stream->close();
        }
    }

    /** @param AggregateRootMetadata<*> $metadata */
    public function save(
        AggregateRootMetadata $metadata,
        string $aggregateId,
        Version|null $expectedVersion,
        Message ...$messages,
    ): SaveResult {
        $tag = $this->tag($metadata, $aggregateId);

        $messages = array_map(
            static function (Message $message) use ($tag): Message {
                $tags = $message->hasHeader(TagsHeader::class)
                    ? $message->header(TagsHeader::class)->tags
                    : [];

                return $message->withHeader(new TagsHeader(array_values(array_unique([...$tags, $tag]))));
            },
            array_values($messages),
        );

        try {
            $index = $this->store->append(
                $messages,
                new AppendCondition(
                    $this->query($metadata, $aggregateId),
                    $expectedVersion->value ?? 0,
                ),
            );
        } catch (AppendConditionNotMet $exception) {
            throw new VersionConflict($exception);
        }

        return new SaveResult($messages, new Version($index));
    }

    /** @param AggregateRootMetadata<*> $metadata */
    private function query(AggregateRootMetadata $metadata, string $aggregateId, bool $onlyLastEvent = false): Query
    {
        return new Query(
            new SubQuery(
                tags: [$this->tag($metadata, $aggregateId)],
                onlyLastEvent: $onlyLastEvent,
            ),
        );
    }

    /** @param AggregateRootMetadata<*> $metadata */
    private function tag(AggregateRootMetadata $metadata, string $aggregateId): string
    {
        return $metadata->name . ':' . $aggregateId;
    }
}
