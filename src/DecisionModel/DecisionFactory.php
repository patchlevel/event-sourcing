<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use Patchlevel\EventSourcing\Store\AppendStore;

/** @experimental */
final class DecisionFactory
{
    private readonly EventAppender $eventAppender;

    public function __construct(
        private readonly AppendStore $store,
        EventAppender|null $eventAppender = null,
    ) {
        $this->eventAppender = $eventAppender ?? new StoreEventAppender($store);
    }

    public function begin(): Decision
    {
        return new Decision($this->store, $this->eventAppender);
    }
}
