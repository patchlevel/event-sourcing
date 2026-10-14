<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface MessageLoader
{
    /** @param list<Subscription> $subscriptions */
    public function load(int|null $startIndex, array $subscriptions): Stream;

    /**
     * Loads only the messages with the given indices in ascending order, e.g. the inbox of a partition.
     *
     * @param non-empty-list<int> $indices
     */
    public function loadIndices(array $indices): Stream;

    public function lastIndex(): int;
}
