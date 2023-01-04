<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;
use Throwable;

use function sprintf;

final class SnapshotNotFound extends SnapshotException
{
    /**
     * @param class-string<AggregateRootInterface> $aggregate
     */
    public function __construct(string $aggregate, string $id, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'snapshot for aggregate "%s" with the id "%s" not found',
                $aggregate,
                $id
            ),
            0,
            $previous
        );
    }
}
