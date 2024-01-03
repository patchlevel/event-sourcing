<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class PlayheadMismatch extends RepositoryException
{
    public function __construct(string $aggregateRootClass, AggregateRootId $aggregateRootId, int $playhead, int $eventCount)
    {
        parent::__construct(sprintf(
            'There is a mismatch between the playhead [%s] and the event count [%s] for the aggregate [%s] with the id [%s]',
            $playhead,
            $eventCount,
            $aggregateRootClass,
            $aggregateRootId->toString(),
        ));
    }
}
