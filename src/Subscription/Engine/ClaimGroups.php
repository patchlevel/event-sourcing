<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

/**
 * How many subscriptions and partitions are claimed and processed together in one transaction.
 *
 * @internal
 */
final class ClaimGroups
{
    /** Subscriptions further apart than that are not processed together, if there is no message limit. */
    public const DEFAULT_POSITION_WINDOW = 1000;

    public function __construct(
        /**
         * How many subscriptions that are not partitions are claimed and processed together in one transaction.
         * They read the stream once from the lowest position of the group. With 1 every subscription is processed on
         * its own.
         *
         * @var positive-int
         */
        public readonly int $subscriptions = 1,
        /**
         * How many partitions are claimed and processed together in one transaction. A partition only reads the
         * messages of its inbox, so many small partitions are cheaper when they share one transaction.
         *
         * @var positive-int
         */
        public readonly int $partitions = 1,
        /**
         * Subscriptions are only grouped if their positions are at most that far apart. The group reads the stream
         * from its lowest position, so a subscription far behind would use up the message limit of the others.
         *
         * @var positive-int
         */
        public readonly int $positionWindow = self::DEFAULT_POSITION_WINDOW,
    ) {
    }
}
