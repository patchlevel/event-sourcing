<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

/** Figures about the inboxes of the partitions that match a criteria, see {@see PartitionInboxStore}. */
final class InboxStats
{
    public function __construct(
        /** number of messages waiting in the inboxes */
        public readonly int $messages = 0,
        /** number of partitions with messages waiting in their inbox */
        public readonly int $partitions = 0,
    ) {
    }
}
