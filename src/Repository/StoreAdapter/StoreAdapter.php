<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;

/**
 * Encapsulates how the repository reads and writes the stream of an aggregate,
 * so the repository does not have to know how a store organizes its data.
 */
interface StoreAdapter
{
    /**
     * Without $fromPlayhead, all messages of the stream that are not archived are loaded.
     * With $fromPlayhead, all messages after this playhead are loaded, archived or not.
     */
    public function load(string $streamName, int|null $fromPlayhead = null): Stream;

    public function has(string $streamName): bool;

    /**
     * If a message has a StreamStartHeader, all previous messages of the stream have to be archived
     * in the same transaction.
     *
     * @throws UniqueConstraintViolation if a playhead of the stream already exists.
     */
    public function save(string $streamName, Message ...$messages): void;
}
