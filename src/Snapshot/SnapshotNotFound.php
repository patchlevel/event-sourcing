<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Throwable;

use function sprintf;

final class SnapshotNotFound extends SnapshotException
{
    /** @param class-string<AggregateRoot> $aggregate */
    public function __construct(string $aggregate, AggregateRootId $id, Throwable|null $previous = null)
    {
        parent::__construct(
            sprintf(
                'snapshot for aggregate "%s" with the id "%s" not found',
                $aggregate,
                $id->toString(),
            ),
            0,
            $previous,
        );
    }
}
