<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Countable;
use IteratorAggregate;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Traversable;

use function array_filter;
use function array_values;
use function count;

/**
 * @interal
 * @implements IteratorAggregate<Subscription>
 */
final class SubscriptionCollection implements IteratorAggregate, Countable
{
    /** @param list<Subscription> $subscriptions */
    public function __construct(
        private array $subscriptions = [],
    ) {
    }

    /** @return Traversable<Subscription> */
    public function getIterator(): Traversable
    {
        yield from $this->subscriptions;
    }

    public function remove(Subscription $subscription): void
    {
        $this->subscriptions = array_values(
            array_filter(
                $this->subscriptions,
                static fn (Subscription $s) => $s !== $subscription,
            ),
        );
    }

    public function count(): int
    {
        return count($this->subscriptions);
    }

    public function lowestPosition(): int
    {
        $min = null;

        foreach ($this->subscriptions as $subscription) {
            if ($min !== null && $subscription->position() >= $min) {
                continue;
            }

            $min = $subscription->position();
        }

        if ($min === null) {
            return 0;
        }

        return $min;
    }
}
