<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Subscription;

/** A message loader that can not load messages by index, like a custom one that only implements the base interface. */
final class PlainMessageLoader implements MessageLoader
{
    public function __construct(
        private readonly MessageLoader $loader,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int|null $startIndex, array $subscriptions): Stream
    {
        return $this->loader->load($startIndex, $subscriptions);
    }

    public function lastIndex(): int
    {
        return $this->loader->lastIndex();
    }
}
