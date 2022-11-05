<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function sprintf;

final class PlayheadMismatch extends RepositoryException
{
    public function __construct(string $aggregateClass, string $aggregateId, int $playhead, int $eventCount)
    {
        parent::__construct(sprintf(
            'There is a mismatch between the playhead [%s] and the event count [%s] for the aggregate [%s] with the id [%s]',
            $playhead,
            $eventCount,
            $aggregateClass,
            $aggregateId
        ));
    }
}
