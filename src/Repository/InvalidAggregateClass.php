<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function sprintf;

/** @deprecated */
final class InvalidAggregateClass extends RepositoryException
{
    public static function notAggregateRoot(string $aggregateClass): self
    {
        return new self(sprintf(
            'Class "%s" is not an AggregateRoot.',
            $aggregateClass,
        ));
    }

    public static function notSnapshotableAggregateRoot(string $aggregateClass): self
    {
        return new self(sprintf(
            'Class "%s" is not a SnapshotableAggregateRoot.',
            $aggregateClass,
        ));
    }
}
