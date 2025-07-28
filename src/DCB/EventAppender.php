<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

/** @experimental */
interface EventAppender
{
    /** @param iterable<object> $events */
    public function append(iterable $events, AppendCondition|null $appendCondition = null): void;
}
