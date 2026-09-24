<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;

/**
 * Encapsulates how the repository reads and writes the events of an aggregate,
 * so the repository does not have to know how a store organizes its data.
 *
 * @experimental
 */
interface StoreAdapter
{
    /**
     * Loads the messages the aggregate has to be rebuilt from.
     * With $fromPlayhead, only the messages after this playhead are loaded.
     *
     * @param AggregateRootMetadata<*> $metadata
     * @param int<0, max>|null         $fromPlayhead
     */
    public function load(AggregateRootMetadata $metadata, string $aggregateId, int|null $fromPlayhead = null): LoadedStream;

    /** @param AggregateRootMetadata<*> $metadata */
    public function has(AggregateRootMetadata $metadata, string $aggregateId): bool;

    /**
     * Saves the messages if the stream is still at the expected version.
     * Without an expected version, the stream must not exist yet.
     *
     * If a message has a StreamStartHeader, all previous messages are not loaded anymore.
     *
     * @param AggregateRootMetadata<*> $metadata
     *
     * @throws VersionConflict
     */
    public function save(
        AggregateRootMetadata $metadata,
        string $aggregateId,
        Version|null $expectedVersion,
        Message ...$messages,
    ): SaveResult;
}
