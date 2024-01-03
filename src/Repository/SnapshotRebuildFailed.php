<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Throwable;

use function sprintf;

final class SnapshotRebuildFailed extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateRootClass */
    public function __construct(
        private string $aggregateRootClass,
        private AggregateRootId $aggregateRootId,
        Throwable $previous,
    ) {
        parent::__construct(
            sprintf(
                'Rebuild from snapshot of aggregate "%s" with the id "%s" failed',
                $aggregateRootClass,
                $aggregateRootId->toString(),
            ),
            0,
            $previous,
        );
    }

    /** @return class-string<AggregateRoot> */
    public function aggregateClass(): string
    {
        return $this->aggregateRootClass;
    }

    public function aggregateRootId(): AggregateRootId
    {
        return $this->aggregateRootId;
    }
}
