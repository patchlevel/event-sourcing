<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Throwable;

use function sprintf;

final class SnapshotRebuildFailed extends RepositoryException
{
    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;
    private string $aggregateId;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    public function __construct(string $aggregateClass, string $aggregateId, Throwable $previous)
    {
        parent::__construct(
            sprintf(
                'Rebuild from snapshot of aggregate "%s" with the id "%s" failed',
                $aggregateClass,
                $aggregateId
            ),
            0,
            $previous
        );

        $this->aggregateClass = $aggregateClass;
        $this->aggregateId = $aggregateId;
    }

    /**
     * @return class-string<AggregateRoot>
     */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }
}
