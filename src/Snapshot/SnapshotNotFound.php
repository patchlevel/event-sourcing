<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Throwable;
use function sprintf;

final class SnapshotNotFound extends SnapshotException
{
    /** @param class-string<AggregateRoot> $aggregateRootClass */
    public function __construct(string $aggregateRootClass, Identifier $rootId, Throwable|null $previous = null)
    {
        parent::__construct(
            sprintf(
                'snapshot for aggregate "%s" with the id "%s" not found',
                $aggregateRootClass,
                $rootId->toString(),
            ),
            0,
            $previous,
        );
    }
}
