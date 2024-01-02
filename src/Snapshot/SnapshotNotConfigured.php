<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function sprintf;

final class SnapshotNotConfigured extends SnapshotException
{
    /** @param class-string<AggregateRoot> $aggregateRootClass */
    public function __construct(string $aggregateRootClass)
    {
        parent::__construct(
            sprintf(
                'Missing snapshot configuration for the aggregate class "%s"',
                $aggregateRootClass,
            ),
        );
    }
}
