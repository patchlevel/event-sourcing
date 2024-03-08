<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

/** @psalm-immutable */
final class SubscriptionEngineCriteria
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
    ) {
    }
}
