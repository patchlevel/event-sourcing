<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;

/** @experimental */
interface AppendStore
{
    /** @param iterable<Message> $messages */
    public function append(
        iterable $messages,
        AppendCondition|null $appendCondition = null,
    ): void;

    /**
     * Returns all events matching the query, starting at the given index (inclusive).
     *
     * @param positive-int|0 $from
     */
    public function query(Query $query, int $from = 0): Stream;
}
