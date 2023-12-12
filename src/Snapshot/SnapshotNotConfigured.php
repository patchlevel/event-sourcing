<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function sprintf;

final class SnapshotNotConfigured extends SnapshotException
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(string $aggregateClass)
    {
        parent::__construct(
            sprintf(
                'Missing snapshot configuration for the aggregate class "%s"',
                $aggregateClass,
            ),
        );
    }
}
