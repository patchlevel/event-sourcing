<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function sprintf;

final class AggregateDetached extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(string $aggregateClass, string $aggregateId)
    {
        parent::__construct(
            sprintf(
                'An error occurred while saving the aggregate "%s" with the ID "%s", causing the uncommitted events to be lost. Please reload the aggregate.',
                $aggregateClass,
                $aggregateId,
            ),
        );
    }
}
