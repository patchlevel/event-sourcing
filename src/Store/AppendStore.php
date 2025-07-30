<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Message;

interface AppendStore
{
    /** @param iterable<Message> $messages */
    public function append(
        iterable $messages,
        AppendCondition|null $appendCondition = null,
    ): void;

    public function query(Query $query): Stream;
}
