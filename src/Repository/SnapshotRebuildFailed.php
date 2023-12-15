<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Throwable;

use function sprintf;

final class SnapshotRebuildFailed extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(
        private string $aggregateClass,
        private AggregateRootId $aggregateId,
        Throwable $previous,
    ) {
        parent::__construct(
            sprintf(
                'Rebuild from snapshot of aggregate "%s" with the id "%s" failed',
                $aggregateClass,
                $aggregateId->toString(),
            ),
            0,
            $previous,
        );
    }

    /** @return class-string<AggregateRoot> */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function aggregateId(): AggregateRootId
    {
        return $this->aggregateId;
    }
}
