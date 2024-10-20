<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateDetached extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateRootClass */
    public function __construct(string $aggregateRootClass, AggregateRootId $aggregateRootId)
    {
        parent::__construct(
            sprintf(
                'An error occurred while saving the aggregate "%s" with the ID "%s", causing the uncommitted events to be lost. Please reload the aggregate.',
                $aggregateRootClass,
                $aggregateRootId->toString(),
            ),
        );
    }
}
