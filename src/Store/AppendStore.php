<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;

/** @experimental */
interface AppendStore
{
    /**
     * @param iterable<Message> $messages
     *
     * @return int<0, max> the index of the last event in the store after the append
     */
    public function append(
        iterable $messages,
        AppendCondition|null $appendCondition = null,
    ): int;

    public function query(Query $query): Stream;
}
