<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface MessageLoader
{
    /** @param list<Subscription> $subscriptions */
    public function load(int|null $startIndex, array $subscriptions): Stream;

    public function lastIndex(): int;
}
