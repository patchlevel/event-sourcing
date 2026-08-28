<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use InvalidArgumentException;

/** @experimental */
final class AppendCondition
{
    public function __construct(
        public readonly Query $query = new Query(),
        public readonly int|null $highestSequenceNumber = null,
    ) {
        if ($query->subQueries !== [] && $highestSequenceNumber === null) {
            throw new InvalidArgumentException(
                'An AppendCondition with a non-empty query needs a highestSequenceNumber. '
                . 'Pass 0 to require that no matching event exists yet.',
            );
        }
    }
}
