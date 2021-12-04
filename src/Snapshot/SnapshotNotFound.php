<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use RuntimeException;

use function sprintf;

final class SnapshotNotFound extends RuntimeException
{
    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function __construct(string $aggregate, string $id)
    {
        parent::__construct(
            sprintf(
                'snapshot for aggregate "%s" with the id "%s" not found',
                $aggregate,
                $id
            )
        );
    }
}
