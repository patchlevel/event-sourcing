<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Store\AppendCondition;

/** @experimental */
interface EventAppender
{
    /** @param iterable<object> $events */
    public function append(
        iterable $events,
        AppendCondition|null $appendCondition = null,
        string|null $streamName = null,
    ): void;
}
